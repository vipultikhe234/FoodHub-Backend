<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Services\Inventory\ProductService;
use App\Services\Identity\FCMService;
use App\Http\Requests\ProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\Review;
use App\Http\Resources\ReviewResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class ProductController extends Controller
{
    public function __construct(
        protected ProductService $service,
        protected FCMService $fcmService
    ) {}

    /**
     * Lookup product by Barcode (Open Food Facts API Proxy)
     */
    public function lookupBarcode($barcode)
    {
        try {
            $response = Http::get("https://world.openfoodfacts.org/api/v0/product/{$barcode}.json");

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['status']) && $data['status'] === 1) {
                    $prod = $data['product'];
                    return response()->json([
                        'status' => 'success',
                        'data' => [
                            'name' => $prod['product_name'] ?? ($prod['product_name_en'] ?? 'Unknown Product'),
                            'image' => $prod['image_url'] ?? ($prod['image_front_url'] ?? null),
                            'brand' => $prod['brands'] ?? null,
                            'category_guess' => $prod['categories_tags'][0] ?? null,
                            'barcode' => $barcode
                        ]
                    ]);
                }
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Product not found in global database'
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'API Error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function index(Request $request)
    {
        $user = $request->user('sanctum');
        $role = strtolower($user?->role ?? 'guest');
        
        // STRICT LOCK: If users are merchants, they MUST only see their own data
        // They cannot override this with the merchant_id query param.
        $isMerchant = ($role === 'merchant');
        $targetId = $isMerchant ? $user->merchant?->id : $request->query('merchant_id');
        
        $cityId = $request->query('city_id');

        // Bypassing city lockdown for Management Console (Admins & Merchants)
        // They should see their whole inventory regardless of geography.
        if ($user && in_array($role, ['merchant', 'admin', 'super_admin'])) {
            $cityId = null;
        }

        if ($isMerchant && !$targetId) {
             return ProductResource::collection(collect([]));
        }

        // Multidimensional Cache Key
        $cacheKey = "products_r_" . ($targetId ?? 'all') . "_city_" . ($cityId ?? 'all') . "_role_" . $role;

        return Cache::remember($cacheKey, 3600, function () use ($targetId, $cityId) {
            $products = $this->service->getAllProducts($targetId, $cityId);
            return ProductResource::collection($products);
        });
    }

    public function curated(Request $request)
    {
        $cityId = $request->query('city_id');
        $products = $this->service->getCuratedProducts($cityId);
        return ProductResource::collection($products);
    }

    public function show($id)
    {
        $product = $this->service->getProductById($id);

        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }
        return new ProductResource($product);
    }

    private function clearProductCache($merchantId = null, $productId = null)
    {
        $roles = ['guest', 'user', 'customer', 'merchant', 'admin', 'super_admin', 'Admin', 'Super Admin'];
        $cities = ['all']; // City ID as string or 'all'
        
        $targets = ['all'];
        if ($merchantId) $targets[] = (string)$merchantId;

        // Clear every possible combination of cached product lists
        foreach ($targets as $target) {
            foreach ($cities as $city) {
                foreach ($roles as $role) {
                    Cache::forget("products_r_{$target}_city_{$city}_role_{$role}");
                    Cache::forget("products_r_{$target}_role_{$role}");
                }
            }
        }
        
        if ($productId) Cache::forget("product_{$productId}");
        
        Cache::forget('products_all');
        Cache::forget('products_active');
    }

    public function store(ProductRequest $request)
    {
        $data = $request->validated();

        // Ownership Assignment - Strictly enforced
        if (in_array(strtolower($request->user()->role), ['merchant'])) {
            $data['merchant_id'] = $request->user()->merchant?->id;
            if (!$data['merchant_id']) return response()->json(['message' => 'Linked Merchant Profile not found'], 400);
        } elseif ($request->has('merchant_id')) {
            $data['merchant_id'] = $request->merchant_id;
        }

        $product = $this->service->createProduct($data);
        $this->clearProductCache($product->merchant_id);

        // Broadcast refresh to mobile side
        $this->fcmService->broadcastData(['type' => 'refresh_products', 'action' => 'created', 'id' => (string)$product->id]);

        return new ProductResource($product);
    }

    public function update(ProductRequest $request, $id)
    {
        $product = Product::find($id);
        if (!$product) return response()->json(['message' => 'Product not found'], 404);

        // Merchant Ownership Check
        if ($request->user()->role === 'merchant' && $product->merchant_id !== $request->user()->merchant?->id) {
            return response()->json(['message' => 'Unauthorized access to this product'], 403);
        }

        $updated = $this->service->updateProduct($id, $request->validated());
        $this->clearProductCache($product->merchant_id, $id);

        // Broadcast refresh to mobile side
        $this->fcmService->broadcastData(['type' => 'refresh_products', 'action' => 'updated', 'id' => (string)$id]);

        return response()->json(['message' => 'Product updated successfully']);
    }

    public function destroy(Request $request, $id)
    {
        $product = Product::find($id);
        if (!$product) return response()->json(['message' => 'Product not found'], 404);

        // Merchant Ownership Check
        if ($request->user()->role === 'merchant' && $product->merchant_id !== $request->user()->merchant?->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $resId = $product->merchant_id;
        $this->service->deleteProduct($id);
        $this->clearProductCache($resId, $id);

        // Broadcast refresh to mobile side
        $this->fcmService->broadcastData(['type' => 'refresh_products', 'action' => 'deleted', 'id' => (string)$id]);

        return response()->json(['message' => 'Product deleted successfully']);
    }

    public function addReview(Request $request, $id)
    {
        $request->validate([
            'order_id'   => 'nullable|integer',
            'rating'     => 'required|integer|min:1|max:5',
            'review'     => 'nullable|string|max:1000',
            'comment'    => 'nullable|string|max:1000'
        ]);

        $product = Product::findOrFail($id);
        $merchantId = $product->merchant_id;
        $orderId = $request->order_id;
        $reviewText = $request->review ?? $request->comment;

        $review = Review::updateOrCreate(
            [
                'user_id' => $request->user()->id, 
                'product_id' => $id,
                'merchant_id' => $merchantId,
                'order_id' => $orderId
            ],
            [
                'rating' => $request->rating,
                'review' => $reviewText,
            ]
        );

        return response()->json([
            'message' => 'Review submitted successfully',
            'data'    => new ReviewResource($review->load('user'))
        ]);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv',
            'merchant_id' => 'nullable|exists:merchants,id'
        ]);

        $merchantId = $request->merchant_id;
        if ($request->user()->role === 'merchant') {
            $merchantId = $request->user()->merchant?->id;
        }

        try {
            \Maatwebsite\Excel\Facades\Excel::import(new \App\Imports\ProductsImport($merchantId), $request->file('file'));
            $this->clearProductCache($merchantId);
            
            return response()->json(['message' => 'Products imported successfully']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Import failed: ' . $e->getMessage()], 400);
        }
    }
}


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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    public function __construct(
        protected ProductService $service,
        protected FCMService $fcmService
    ) {}

    public function index(Request $request)
    {
        $role = $request->user('sanctum')?->role ?? 'guest';
        $targetId = $request->query('merchant_id');

        // Multidimensional Cache Key
        $cacheKey = "products_r_" . ($targetId ?? 'all') . "_role_" . $role;

        return Cache::remember($cacheKey, 3600, function () use ($targetId) {
            $products = $this->service->getAllProducts($targetId);
            return ProductResource::collection($products);
        });
    }

    public function curated()
    {
        $products = $this->service->getCuratedProducts();
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

    private function clearProductCache($MerchantId = null, $productId = null)
    {
        $roles = ['guest', 'merchant', 'admin', 'super_admin', 'Admin', 'Super Admin'];
        foreach ($roles as $role) {
            if ($MerchantId) {
                Cache::forget("products_r_{$MerchantId}_role_{$role}");
            }
            Cache::forget("products_r_all_role_{$role}");
            Cache::forget("products_r__role_{$role}"); // Catch null case
        }
        
        Cache::forget('products_all');
        if ($productId) {
            Cache::forget("product_{$productId}");
        }
    }

    public function store(ProductRequest $request)
    {
        $data = $request->validated();

        // Ownership Assignment
        if ($request->user()->role === 'merchant') {
            $data['merchant_id'] = $request->user()->merchant?->id;
            if (!$data['merchant_id']) return response()->json(['message' => 'No Merchant found'], 400);
        } elseif (in_array($request->user()->role, ['admin', 'super_admin', 'Admin', 'Super Admin']) && $request->has('merchant_id')) {
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
            'order_id'   => 'required|integer|exists:orders,id',
            'rating'     => 'required|integer|min:1|max:5',
            'review'     => 'nullable|string|max:1000'
        ]);

        // Derive merchant from the product
        $product = Product::findOrFail($id);
        $merchantId = $product->merchant_id;

        // Verify the order belongs to this user and merchant, and is delivered
        $order = \App\Models\Order::where('id', $request->order_id)
            ->where('user_id', $request->user()->id)
            ->where('merchant_id', $merchantId)
            ->where('status', 'delivered')
            ->firstOrFail();

        // One review per order (enforced by DB unique constraint on user_id + order_id)
        $review = Review::updateOrCreate(
            ['user_id' => $request->user()->id, 'order_id' => $order->id, 'product_id' => $id],
            [
                'merchant_id' => $merchantId,
                'rating'      => $request->rating,
                'review'      => $request->review,
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


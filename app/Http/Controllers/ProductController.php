<?php

namespace App\Http\Controllers;

use App\Services\ProductService;
use App\Http\Requests\ProductRequest;
use App\Http\Resources\ProductResource;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ProductController extends Controller
{
    public function __construct(
        protected ProductService $service,
        protected \App\Services\FCMService $fcmService
    ) {}

    public function index()
    {
        // Cache product list for 5 minutes — biggest read endpoint
        $products = Cache::remember('products_all', 300, function () {
            return $this->service->getAllProducts();
        });
        return ProductResource::collection($products);
    }



    public function show($id)
    {
        $product = Cache::remember("product_{$id}", 300, function () use ($id) {
            return $this->service->getProductById($id);
        });

        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }
        return new ProductResource($product);
    }

    public function store(ProductRequest $request)
    {
        $product = $this->service->createProduct($request->validated());
        Cache::forget('products_all');     // Bust cache on write
        Cache::forget('categories_active');

        // Broadcast refresh to mobile side
        $this->fcmService->broadcastData(['type' => 'refresh_products', 'action' => 'created', 'id' => (string)$product->id]);

        return new ProductResource($product);
    }

    public function update(ProductRequest $request, $id)
    {
        $updated = $this->service->updateProduct($id, $request->validated());
        if (!$updated) {
            return response()->json(['message' => 'Product not found'], 404);
        }
        Cache::forget('products_all');     // Bust cache on write
        Cache::forget("product_{$id}");

        // Broadcast refresh to mobile side
        $this->fcmService->broadcastData(['type' => 'refresh_products', 'action' => 'updated', 'id' => (string)$id]);

        return response()->json(['message' => 'Product updated successfully']);
    }

    public function destroy($id)
    {
        $deleted = $this->service->deleteProduct($id);
        if (!$deleted) {
            return response()->json(['message' => 'Product not found'], 404);
        }
        Cache::forget('products_all');     // Bust cache on write
        Cache::forget("product_{$id}");

        // Broadcast refresh to mobile side
        $this->fcmService->broadcastData(['type' => 'refresh_products', 'action' => 'deleted', 'id' => (string)$id]);

        return response()->json(['message' => 'Product deleted successfully']);
    }

    public function addReview(Request $request, $id)
    {
        $request->validate([
            'rating'  => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:500'
        ]);

        $review = \App\Models\Review::updateOrCreate(
            ['user_id' => $request->user()->id, 'product_id' => $id],
            ['rating'  => $request->rating, 'comment' => $request->comment]
        );

        // Bust product cache so new rating shows
        Cache::forget("product_{$id}");

        return response()->json([
            'message' => 'Review submitted successfully',
            'data'    => new \App\Http\Resources\ReviewResource($review)
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use App\Http\Resources\CategoryResource;
use App\Http\Requests\CategoryRequest;
use App\Services\CategoryService;

class CategoryController extends Controller
{
    public function __construct(
        protected CategoryService $service,
        protected \App\Services\FCMService $fcmService
    ) {}

    public function index()
    {
        $categories = \Illuminate\Support\Facades\Cache::remember('categories_all', 600, function () {
            return $this->service->getAllCategories();
        });
        return CategoryResource::collection($categories);
    }

    public function store(CategoryRequest $request)
    {
        $category = $this->service->createCategory($request->validated());
        \Illuminate\Support\Facades\Cache::forget('categories_all');
        \Illuminate\Support\Facades\Cache::forget('categories_active');

        // Broadcast refresh
        $this->fcmService->broadcastData(['type' => 'refresh_categories', 'action' => 'created', 'id' => (string)$category->id]);

        return new CategoryResource($category);
    }

    public function update(CategoryRequest $request, $id)
    {
        $updated = $this->service->updateCategory($id, $request->validated());
        if (!$updated) {
            return response()->json(['message' => 'Category not found'], 404);
        }
        \Illuminate\Support\Facades\Cache::forget('categories_all');
        \Illuminate\Support\Facades\Cache::forget('categories_active');

        // Broadcast refresh
        $this->fcmService->broadcastData(['type' => 'refresh_categories', 'action' => 'updated', 'id' => (string)$id]);

        return response()->json(['message' => 'Category updated successfully']);
    }

    public function destroy($id)
    {
        $deleted = $this->service->deleteCategory($id);
        if (!$deleted) {
            return response()->json(['message' => 'Category not found'], 404);
        }
        \Illuminate\Support\Facades\Cache::forget('categories_all');
        \Illuminate\Support\Facades\Cache::forget('categories_active');

        // Broadcast refresh
        $this->fcmService->broadcastData(['type' => 'refresh_categories', 'action' => 'deleted', 'id' => (string)$id]);

        return response()->json(['message' => 'Category deleted']);
    }
}

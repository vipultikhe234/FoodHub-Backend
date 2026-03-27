<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use App\Http\Resources\CategoryResource;
use App\Http\Requests\CategoryRequest;
use App\Services\Inventory\CategoryService;
use App\Services\Identity\FCMService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CategoryController extends Controller
{
    public function __construct(
        protected CategoryService $service,
        protected FCMService $fcmService
    ) {}

    public function index(Request $request)
    {
        $role = $request->user('sanctum')?->role ?? 'guest';
        $targetId = $request->query('merchant_id');

        $cacheKey = "categories_r_" . ($targetId ?? 'all') . "_role_" . $role;

        return Cache::remember($cacheKey, 3600, function () use ($targetId, $request) {
            if ($request->has('unique') && $request->unique) {
                // Get one record per name (unique public categories)
                return CategoryResource::collection(
                    Category::select('name')
                        ->selectRaw('MAX(id) as id, MAX(image) as image')
                        ->groupBy('name')
                        ->get()
                );
            }
            $categories = $this->service->getAllCategories($targetId);
            return CategoryResource::collection($categories);
        });
    }

    private function clearCategoryCache($MerchantId = null)
    {
        $roles = ['guest', 'merchant', 'admin', 'super_admin', 'Admin', 'Super Admin'];
        foreach ($roles as $role) {
            if ($MerchantId) {
                Cache::forget("categories_r_{$MerchantId}_role_{$role}");
            }
            Cache::forget("categories_r_all_role_{$role}");
            Cache::forget("categories_r__role_{$role}");
        }
        Cache::forget('categories_active');
    }

    public function store(CategoryRequest $request)
    {
        $data = $request->validated();
        if ($request->user()->role === 'merchant') {
            $data['merchant_id'] = $request->user()->merchant?->id;
        } elseif (in_array($request->user()->role, ['admin', 'super_admin', 'Admin', 'Super Admin']) && $request->has('merchant_id')) {
            $data['merchant_id'] = $request->merchant_id;
        }

        $category = $this->service->createCategory($data);
        $this->clearCategoryCache($category->merchant_id);

        // Broadcast refresh
        $this->fcmService->broadcastData(['type' => 'refresh_categories', 'action' => 'created', 'id' => (string)$category->id]);

        return new CategoryResource($category);
    }

    public function update(CategoryRequest $request, $id)
    {
        $category = Category::find($id);
        if (!$category) return response()->json(['message' => 'Category not found'], 404);

        if ($request->user()->role === 'merchant' && $category->merchant_id !== $request->user()->merchant?->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $this->service->updateCategory($id, $request->validated());
        $this->clearCategoryCache($category->merchant_id);

        // Broadcast refresh
        $this->fcmService->broadcastData(['type' => 'refresh_categories', 'action' => 'updated', 'id' => (string)$id]);

        return response()->json(['message' => 'Category updated successfully']);
    }

    public function destroy(Request $request, $id)
    {
        $category = Category::find($id);
        if (!$category) return response()->json(['message' => 'Category not found'], 404);

        if ($request->user()->role === 'merchant' && $category->merchant_id !== $request->user()->merchant?->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $resId = $category->merchant_id;
        $this->service->deleteCategory($id);
        $this->clearCategoryCache($resId);

        // Broadcast refresh
        $this->fcmService->broadcastData(['type' => 'refresh_categories', 'action' => 'deleted', 'id' => (string)$id]);

        return response()->json(['message' => 'Category deleted']);
    }
}


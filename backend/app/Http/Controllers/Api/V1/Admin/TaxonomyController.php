<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\TaxClass;
use App\Support\Audit\Audit;
use App\Support\Http\ApiResponse;
use App\Support\Storefront\StorefrontCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Categories, brands and the lookups the product editor needs.
 */
class TaxonomyController extends Controller
{
    public function lookups(): JsonResponse
    {
        return ApiResponse::success([
            'categories' => Category::query()->orderBy('sort_order')->get(['uuid', 'name']),
            'brands' => Brand::query()->orderBy('name')->get(['uuid', 'name']),
            'tax_classes' => TaxClass::query()->orderBy('rate_bps')->get(['uuid', 'name', 'rate_bps', 'is_default']),
        ]);
    }

    public function categories(): JsonResponse
    {
        return ApiResponse::success(Category::query()->withCount('products')->orderBy('sort_order')->get()->map(fn (Category $c) => [
            'uuid' => $c->uuid, 'name' => $c->name, 'slug' => $c->slug, 'description' => $c->description, 'sort_order' => $c->sort_order,
            'is_active' => $c->is_active, 'products_count' => $c->products_count,
            'image' => $c->image_path ? Storage::disk('public')->url($c->image_path) : null,
        ]));
    }

    public function saveCategory(Request $request, ?string $category = null): JsonResponse
    {
        $model = $category ? Category::query()->where('uuid', $category)->firstOrFail() : new Category;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('categories', 'slug')->ignore($model->id)],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'is_active' => ['boolean'],
        ]);
        $before = $model->exists ? $model->only(['name', 'slug', 'is_active']) : null;
        $model->fill([...$data, 'slug' => $data['slug'] ?? ($model->slug ?: Str::slug($data['name'])), 'sort_order' => $data['sort_order'] ?? $model->sort_order ?? 0])->save();
        Audit::record($before ? 'category.updated' : 'category.created', $model, $before, $model->only(['name', 'slug', 'is_active']), $request->user());
        StorefrontCache::invalidate(['catalog']);

        return ApiResponse::success(['uuid' => $model->uuid], 'Category saved.', status: $before ? 200 : 201);
    }

    public function deleteCategory(Request $request, string $category): JsonResponse
    {
        $model = Category::query()->where('uuid', $category)->withCount('products')->firstOrFail();
        abort_if($model->products_count > 0, 409, 'Move or archive this category\'s products first.');
        $model->delete();
        Audit::record('category.deleted', $model, $model->only(['name']), null, $request->user());
        StorefrontCache::invalidate(['catalog']);

        return ApiResponse::success(message: 'Category deleted.');
    }

    public function brands(): JsonResponse
    {
        return ApiResponse::success(Brand::query()->withCount('products')->orderBy('name')->get()->map(fn (Brand $b) => [
            'uuid' => $b->uuid, 'name' => $b->name, 'slug' => $b->slug, 'description' => $b->description, 'is_active' => $b->is_active, 'products_count' => $b->products_count,
        ]));
    }

    public function saveBrand(Request $request, ?string $brand = null): JsonResponse
    {
        $model = $brand ? Brand::query()->where('uuid', $brand)->firstOrFail() : new Brand;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('brands', 'slug')->ignore($model->id)],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ]);
        $before = $model->exists ? $model->only(['name', 'slug', 'is_active']) : null;
        $model->fill([...$data, 'slug' => $data['slug'] ?? ($model->slug ?: Str::slug($data['name']))])->save();
        Audit::record($before ? 'brand.updated' : 'brand.created', $model, $before, $model->only(['name', 'slug', 'is_active']), $request->user());
        StorefrontCache::invalidate(['catalog']);

        return ApiResponse::success(['uuid' => $model->uuid], 'Brand saved.', status: $before ? 200 : 201);
    }

    public function deleteBrand(Request $request, string $brand): JsonResponse
    {
        $model = Brand::query()->where('uuid', $brand)->withCount('products')->firstOrFail();
        abort_if($model->products_count > 0, 409, 'This brand still has products.');
        $model->delete();
        Audit::record('brand.deleted', $model, $model->only(['name']), null, $request->user());

        return ApiResponse::success(message: 'Brand deleted.');
    }
}

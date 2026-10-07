<?php

namespace App\Http\Controllers\Api\V1\Storefront;

use App\Domain\Catalog\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use App\Support\Http\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Public catalog (API.md §3.3). Sort and filter keys are whitelisted; values are bound.
 */
class CatalogController extends Controller
{
    private const SORTS = ['featured', 'newest', 'best_selling', 'price_asc', 'price_desc', 'rating'];

    public function categories(): JsonResponse
    {
        $categories = Category::query()->where('is_active', true)->orderBy('sort_order')
            ->withCount(['products' => fn ($q) => $q->visible()])->get();

        return ApiResponse::success(CategoryResource::collection($categories));
    }

    public function category(string $slug): JsonResponse
    {
        $category = Category::query()->where('slug', $slug)->where('is_active', true)->firstOrFail();

        return ApiResponse::success(new CategoryResource($category));
    }

    public function products(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'category' => ['nullable', 'string', 'max:120'],
            'brand' => ['nullable', 'string', 'max:120'],
            'type' => ['nullable', Rule::enum(ProductType::class)],
            'q' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(self::SORTS)],
            'featured' => ['nullable', 'boolean'],
            'new' => ['nullable', 'boolean'],
            'best_seller' => ['nullable', 'boolean'],
            'on_sale' => ['nullable', 'boolean'],
            'price_min' => ['nullable', 'integer', 'min:0'],
            'price_max' => ['nullable', 'integer', 'min:0'],
            'exclude' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:60'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = Product::query()->visible()->with(['brand', 'category', 'media', 'variants.inventory']);

        $query->when($filters['category'] ?? null, fn (Builder $q, string $slug) => $q->whereHas('category', fn (Builder $c) => $c->where('slug', $slug)))
            ->when($filters['brand'] ?? null, fn (Builder $q, string $slug) => $q->whereHas('brand', fn (Builder $b) => $b->where('slug', $slug)))
            ->when($filters['type'] ?? null, fn (Builder $q, string $type) => $q->where('product_type', $type))
            ->when($filters['featured'] ?? false, fn (Builder $q) => $q->where('is_featured', true))
            ->when($filters['new'] ?? false, fn (Builder $q) => $q->where('is_new', true))
            ->when($filters['best_seller'] ?? false, fn (Builder $q) => $q->where('is_best_seller', true))
            ->when($filters['on_sale'] ?? false, fn (Builder $q) => $q->whereColumn('compare_at_price', '>', 'min_price'))
            ->when(isset($filters['price_min']), fn (Builder $q) => $q->where('min_price', '>=', $filters['price_min']))
            ->when(isset($filters['price_max']), fn (Builder $q) => $q->where('min_price', '<=', $filters['price_max']))
            ->when($filters['exclude'] ?? null, fn (Builder $q, string $slug) => $q->where('slug', '!=', $slug));

        if ($term = trim((string) ($filters['q'] ?? ''))) {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';
            $query->where(fn (Builder $q) => $q->where('name', 'like', $like)->orWhere('short_description', 'like', $like)
                ->orWhereHas('brand', fn (Builder $b) => $b->where('name', 'like', $like))
                ->orWhereHas('category', fn (Builder $c) => $c->where('name', 'like', $like))
                ->orWhereHas('variants', fn (Builder $v) => $v->where('sku', 'like', $like)));
        }

        match ($filters['sort'] ?? 'featured') {
            'newest' => $query->orderByDesc('published_at'),
            'best_selling' => $query->orderByDesc('is_best_seller')->orderByDesc('units_sold')->orderByDesc('rating_count'),
            'price_asc' => $query->orderBy('min_price'),
            'price_desc' => $query->orderByDesc('min_price'),
            'rating' => $query->orderByDesc('rating_avg')->orderByDesc('rating_count'),
            default => $query->orderByDesc('is_featured')->orderByDesc('published_at'),
        };
        $query->orderBy('id');

        return ApiResponse::paginated($query->paginate((int) ($filters['per_page'] ?? 24)), ProductResource::class);
    }

    public function product(string $slug): JsonResponse
    {
        $product = Product::query()->visible()->where('slug', $slug)
            ->with(['brand', 'category', 'media', 'variants.inventory', 'digital', 'files'])->firstOrFail();

        return ApiResponse::success((new ProductResource($product))->detailed());
    }
}

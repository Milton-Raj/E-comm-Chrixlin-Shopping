<?php

namespace App\Domain\Catalog;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * "Pairs well with" suggestions: the owner's hand-picked products first, then
 * same-category products, then best sellers. Only visible, in-stock products.
 */
class Recommendations
{
    private const WITH = ['brand', 'category', 'media', 'variants.inventory'];

    /** @return Collection<int, Product> */
    public function forProduct(Product $product, int $limit = 4): Collection
    {
        return $this->build(collect([$product]), collect([$product->id]), $limit);
    }

    /**
     * Suggestions for everything in a bag, never repeating what is already in it.
     *
     * @param  Collection<int, Product>  $products
     * @return Collection<int, Product>
     */
    public function forProducts(Collection $products, int $limit = 4): Collection
    {
        return $products->isEmpty() ? collect() : $this->build($products, $products->pluck('id'), $limit);
    }

    /**
     * @param  Collection<int, Product>  $sources
     * @param  Collection<int, int>  $exclude
     * @return Collection<int, Product>
     */
    private function build(Collection $sources, Collection $exclude, int $limit): Collection
    {
        $picked = Product::query()->visible()->with(self::WITH)
            ->join('related_products', 'related_products.related_product_id', '=', 'products.id')
            ->whereIn('related_products.product_id', $sources->pluck('id'))
            ->where('related_products.type', 'cross_sell')
            ->whereNotIn('products.id', $exclude)
            ->orderBy('related_products.sort_order')
            ->select('products.*')
            ->get()
            ->unique('id');

        $result = $picked->filter(fn (Product $p) => $this->inStock($p))->values();
        $categories = $sources->pluck('category_id')->filter()->unique();

        foreach ([fn (Builder $q) => $q->whereIn('category_id', $categories), fn (Builder $q) => $q->where('is_best_seller', true)] as $scope) {
            if ($result->count() >= $limit) {
                break;
            }
            $more = Product::query()->visible()->with(self::WITH)->tap($scope)
                ->whereNotIn('id', $exclude->merge($result->pluck('id')))
                ->orderByDesc('units_sold')->limit($limit * 2)->get()
                ->filter(fn (Product $p) => $this->inStock($p));
            $result = $result->merge($more)->unique('id')->values();
        }

        return $result->take($limit)->values();
    }

    private function inStock(Product $product): bool
    {
        if (! $product->product_type->tracksInventory()) {
            return true;
        }

        return $product->variants->where('is_active', true)->contains(fn ($v) => ($v->available() ?? 1) > 0);
    }
}

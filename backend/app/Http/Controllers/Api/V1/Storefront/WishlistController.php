<?php

namespace App\Http\Controllers\Api\V1\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\WishlistItem;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = WishlistItem::query()->where('user_id', $request->user()->getKey())
            ->with(['product' => fn ($q) => $q->with(['brand', 'category', 'media', 'variants.inventory'])])->latest('id')->get()
            ->filter(fn (WishlistItem $i) => $i->product !== null);

        return ApiResponse::success($items->map(fn (WishlistItem $i) => [
            'uuid' => $i->uuid,
            'product' => (new ProductResource($i->product))->resolve($request),
        ])->values());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['product_uuid' => ['required', 'uuid']]);
        $product = Product::query()->visible()->where('uuid', $data['product_uuid'])->firstOrFail();
        WishlistItem::query()->firstOrCreate(['user_id' => $request->user()->getKey(), 'product_id' => $product->id], ['price_at_add' => $product->min_price]);

        return ApiResponse::success(message: 'Saved to your wishlist.', status: 201);
    }

    public function destroy(Request $request, string $product): JsonResponse
    {
        WishlistItem::query()->where('user_id', $request->user()->getKey())
            ->whereHas('product', fn ($q) => $q->where('uuid', $product))->delete();

        return ApiResponse::success(message: 'Removed from your wishlist.');
    }
}

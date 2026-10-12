<?php

namespace App\Http\Controllers\Api\V1\Storefront;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockAlert;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** "Email me when it's back" for a sold-out variant (public, rate limited). */
class StockAlertController extends Controller
{
    public function store(Request $request, string $slug): JsonResponse
    {
        $data = $request->validate([
            'variant_uuid' => ['required', 'uuid'],
            'email' => ['required', 'email:rfc', 'max:255'],
        ]);
        $product = Product::query()->visible()->where('slug', $slug)->firstOrFail();
        $variant = $product->variants()->with('inventory')->where('uuid', $data['variant_uuid'])->where('is_active', true)->firstOrFail();

        if (($variant->available() ?? 1) > 0) {
            throw new ApiException('This item is in stock — you can order it now.', 409, 'in_stock');
        }

        $alert = StockAlert::query()->firstOrNew(['variant_id' => $variant->id, 'email' => mb_strtolower(trim($data['email']))]);
        $alert->fill(['product_id' => $product->id, 'user_id' => $request->user()?->getKey() ?? $alert->user_id, 'notified_at' => null])->save();

        return ApiResponse::success(null, "We'll email you as soon as it's back.", status: 201);
    }
}

<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Inventory\InventoryService;
use App\Http\Controllers\Controller;
use App\Models\InventoryTransaction;
use App\Models\ProductVariant;
use App\Support\Audit\Audit;
use App\Support\Http\ApiResponse;
use App\Support\Storefront\StorefrontCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'low' => ['nullable', 'boolean'], 'page' => ['nullable', 'integer']]);

        $rows = DB::table('product_variants')->where('product_variants.track_inventory', true)->whereNull('product_variants.deleted_at')
            ->join('inventory', 'inventory.variant_id', '=', 'product_variants.id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->whereNull('products.deleted_at')
            ->when($filters['q'] ?? null, fn ($q, $t) => $q->where(fn ($w) => $w->where('products.name', 'like', "%{$t}%")->orWhere('product_variants.sku', 'like', "%{$t}%")))
            ->when($filters['low'] ?? false, fn ($q) => $q->whereRaw('inventory.on_hand - inventory.reserved <= inventory.low_stock_threshold'))
            ->orderByRaw('inventory.on_hand - inventory.reserved asc')
            ->select(['product_variants.uuid', 'product_variants.sku', 'product_variants.name as variant_name', 'products.name as product_name', 'products.uuid as product_uuid', 'inventory.on_hand', 'inventory.reserved', 'inventory.low_stock_threshold'])
            ->paginate(50);

        return ApiResponse::success(collect($rows->items())->map(fn ($r) => [
            'variant_uuid' => $r->uuid, 'sku' => $r->sku, 'variant_name' => $r->variant_name, 'product_name' => $r->product_name, 'product_uuid' => $r->product_uuid,
            'on_hand' => (int) $r->on_hand, 'reserved' => (int) $r->reserved, 'available' => max(0, (int) $r->on_hand - (int) $r->reserved),
            'low' => ((int) $r->on_hand - (int) $r->reserved) <= (int) $r->low_stock_threshold,
        ]), meta: ['pagination' => ['page' => $rows->currentPage(), 'per_page' => $rows->perPage(), 'total' => $rows->total(), 'last_page' => $rows->lastPage()]]);
    }

    public function adjust(Request $request, string $variant, InventoryService $inventory): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['received', 'adjustment', 'damage', 'return'])],
            'quantity' => ['required', 'integer', 'not_in:0', 'min:-100000', 'max:100000'],
            'note' => ['nullable', 'string', 'max:200'],
        ]);
        $model = ProductVariant::query()->where('uuid', $variant)->firstOrFail();
        $quantity = $data['type'] === 'damage' ? -abs((int) $data['quantity']) : (int) $data['quantity'];
        $before = $model->inventory?->on_hand;
        $result = $inventory->adjust($model, $data['type'], $quantity, $data['note'] ?? null, $request->user());
        Audit::record('inventory.adjusted', $model, ['on_hand' => $before], ['on_hand' => $result->on_hand, 'type' => $data['type']], $request->user());
        StorefrontCache::invalidate(['catalog']);

        return ApiResponse::success(['on_hand' => $result->on_hand, 'reserved' => $result->reserved, 'available' => $result->available()], 'Stock updated.');
    }

    public function transactions(string $variant): JsonResponse
    {
        $model = ProductVariant::query()->where('uuid', $variant)->firstOrFail();
        $rows = InventoryTransaction::query()->where('variant_id', $model->id)->with('actor')->latest('id')->limit(100)->get();

        return ApiResponse::success($rows->map(fn (InventoryTransaction $t) => [
            'type' => $t->type, 'quantity' => $t->quantity, 'balance_after' => $t->balance_after, 'note' => $t->note,
            'actor' => $t->actor?->name, 'created_at' => $t->created_at->toIso8601String(),
        ]));
    }
}

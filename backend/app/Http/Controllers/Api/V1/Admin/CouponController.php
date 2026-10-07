<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Support\Audit\Audit;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CouponController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponse::success(Coupon::query()->latest('id')->get()->map(fn (Coupon $c) => $this->present($c)));
    }

    public function save(Request $request, ?string $coupon = null): JsonResponse
    {
        $model = $coupon ? Coupon::query()->where('uuid', $coupon)->firstOrFail() : new Coupon;
        $data = $request->validate([
            'code' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('coupons', 'code')->ignore($model->id)],
            'description' => ['nullable', 'string', 'max:200'],
            'type' => ['required', Rule::in(['percentage', 'fixed', 'free_shipping'])],
            'value' => ['required_unless:type,free_shipping', 'nullable', 'integer', 'min:0'],
            'max_discount' => ['nullable', 'integer', 'min:0'],
            'min_order_total' => ['nullable', 'integer', 'min:0'],
            'first_order_only' => ['boolean'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_customer_limit' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'is_active' => ['boolean'],
        ]);
        if ($data['type'] === 'percentage' && ($data['value'] ?? 0) > 10_000) {
            return ApiResponse::error('Validation failed', 422, ['value' => ['A percentage cannot exceed 100%.']]);
        }
        $before = $model->exists ? $this->present($model) : null;
        $model->fill([...$data, 'value' => $data['value'] ?? 0])->save();
        Audit::record($before ? 'coupon.updated' : 'coupon.created', $model, $before, $this->present($model), $request->user());

        return ApiResponse::success($this->present($model), 'Coupon saved.', status: $before ? 200 : 201);
    }

    public function destroy(Request $request, string $coupon): JsonResponse
    {
        $model = Coupon::query()->where('uuid', $coupon)->firstOrFail();
        $model->forceFill(['is_active' => false])->save();
        $model->delete();
        Audit::record('coupon.deleted', $model, ['code' => $model->code], null, $request->user());

        return ApiResponse::success(message: 'Coupon deleted.');
    }

    /** @return array<string, mixed> */
    private function present(Coupon $c): array
    {
        return [
            'uuid' => $c->uuid, 'code' => $c->code, 'description' => $c->description, 'type' => $c->type, 'value' => $c->value,
            'max_discount' => $c->max_discount, 'min_order_total' => $c->min_order_total, 'first_order_only' => $c->first_order_only,
            'usage_limit' => $c->usage_limit, 'usage_count' => $c->usage_count, 'per_customer_limit' => $c->per_customer_limit,
            'starts_at' => $c->starts_at?->toIso8601String(), 'ends_at' => $c->ends_at?->toIso8601String(), 'is_active' => $c->is_active,
        ];
    }
}

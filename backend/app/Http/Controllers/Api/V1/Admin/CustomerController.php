<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Payments\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\DigitalEntitlement;
use App\Models\Order;
use App\Models\User;
use App\Models\WishlistItem;
use App\Support\Audit\Audit;
use App\Support\Http\ApiResponse;
use App\Support\Money\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = (string) $request->query('q', '');
        $currency = (string) config('commerce.store.currency');

        $customers = User::query()->role('customer')
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")))
            ->withCount(['orders' => fn ($o) => $o->where('payment_status', PaymentStatus::Captured->value)])
            ->withSum(['orders as total_spent' => fn ($o) => $o->where('payment_status', PaymentStatus::Captured->value)], 'grand_total')
            ->latest('id')->paginate(25);

        return ApiResponse::success(collect($customers->items())->map(fn (User $u) => [
            'uuid' => $u->uuid, 'name' => $u->name, 'email' => $u->email, 'is_active' => $u->is_active,
            'orders_count' => (int) $u->orders_count, 'total_spent' => Money::of((int) $u->total_spent, $currency)->toArray(),
            'created_at' => $u->created_at?->toIso8601String(), 'last_login_at' => $u->last_login_at?->toIso8601String(),
        ]), meta: ['pagination' => ['page' => $customers->currentPage(), 'per_page' => $customers->perPage(), 'total' => $customers->total(), 'last_page' => $customers->lastPage()]]);
    }

    /** Customer profile with analytics (PRD §74). */
    public function show(string $customer): JsonResponse
    {
        $user = User::query()->where('uuid', $customer)->firstOrFail();
        $currency = (string) config('commerce.store.currency');
        $paid = Order::query()->where('user_id', $user->id)->where('payment_status', PaymentStatus::Captured->value);
        $count = (clone $paid)->count();
        $spent = (int) (clone $paid)->sum('grand_total');

        return ApiResponse::success([
            'uuid' => $user->uuid, 'name' => $user->name, 'email' => $user->email, 'phone' => $user->phone,
            'is_active' => $user->is_active, 'email_verified' => $user->email_verified_at !== null,
            'created_at' => $user->created_at?->toIso8601String(), 'last_login_at' => $user->last_login_at?->toIso8601String(),
            'stats' => [
                'total_orders' => $count,
                'total_spent' => Money::of($spent, $currency)->toArray(),
                'average_order_value' => Money::of($count ? intdiv($spent, $count) : 0, $currency)->toArray(),
                'last_purchase_at' => (clone $paid)->max('paid_at'),
                'wishlist_items' => WishlistItem::query()->where('user_id', $user->id)->count(),
                'downloads' => (int) DigitalEntitlement::query()->where('user_id', $user->id)->sum('downloads_used'),
                'refunded_orders' => Order::query()->where('user_id', $user->id)->where('refunded_total', '>', 0)->count(),
            ],
            'orders' => OrderResource::collection(Order::query()->where('user_id', $user->id)->whereNotNull('placed_at')->with('items.product')->latest('placed_at')->limit(20)->get())->resolve(),
        ]);
    }

    public function update(Request $request, string $customer): JsonResponse
    {
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $user = User::query()->where('uuid', $customer)->firstOrFail();
        abort_if($user->isStaff(), 403);
        $before = ['is_active' => $user->is_active];
        $user->forceFill($data)->save();
        if (! $data['is_active']) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }
        Audit::record($data['is_active'] ? 'customer.unblocked' : 'customer.blocked', $user, $before, $data, $request->user());

        return ApiResponse::success(message: $data['is_active'] ? 'Customer unblocked.' : 'Customer blocked.');
    }
}

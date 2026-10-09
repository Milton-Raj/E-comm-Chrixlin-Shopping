<?php

namespace App\Domain\Checkout;

use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\User;

/**
 * A signed-in customer's delivery address from their latest order, offered at checkout
 * for them to confirm (never applied silently). Guests get nothing saved.
 */
class SavedAddress
{
    /** @return array<string, string|null>|null */
    public function forUser(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        $address = OrderAddress::query()
            ->where('type', 'shipping')
            ->whereIn('order_id', Order::query()->select('id')->where('user_id', $user->getKey())->whereNotNull('placed_at'))
            ->orderByDesc('order_id')
            ->first();

        return $address ? $address->only(['name', 'phone', 'line1', 'line2', 'city', 'state_code', 'postal_code', 'country_code']) : null;
    }
}

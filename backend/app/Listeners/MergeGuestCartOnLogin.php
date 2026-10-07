<?php

namespace App\Listeners;

use App\Domain\Cart\CartService;
use App\Models\User;
use Illuminate\Auth\Events\Login;

/**
 * Guest cart + customer cart = merged cart (PRD §21). Discovered automatically.
 */
class MergeGuestCartOnLogin
{
    public function __construct(private readonly CartService $carts) {}

    public function handle(Login $event): void
    {
        if ($event->user instanceof User && app()->bound('request')) {
            $this->carts->mergeGuestCart(request()->cookie(CartService::COOKIE), $event->user);
        }
    }
}

<?php

use App\Domain\Marketing\Actions\SendAbandonedCartReminders;
use App\Models\Cart;
use App\Models\User;
use App\Notifications\AbandonedCartNotification;
use App\Support\Settings\Settings;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

function abandonGuestBag($test, string $email = 'guest@example.test'): Cart
{
    $test->postJson('/api/v1/cart/items', ['variant_uuid' => variantOf(makeProduct(price: 150_000))->uuid, 'quantity' => 2])->assertCreated();
    $test->putJson('/api/v1/checkout/contact', ['email' => $email])->assertOk();
    $cart = Cart::query()->latest('id')->firstOrFail();
    $cart->forceFill(['last_activity_at' => now()->subHours(5)])->save();

    return $cart;
}

it('does nothing until the owner switches reminders on', function () {
    Notification::fake();
    abandonGuestBag($this);

    expect(app(SendAbandonedCartReminders::class)())->toBe(0);
    Notification::assertNothingSent();
});

it('emails one reminder per abandoned bag after the delay', function () {
    Notification::fake();
    app(Settings::class)->set('marketing.abandoned_cart_enabled', true);
    app(Settings::class)->set('marketing.abandoned_cart_delay_hours', 3);
    $cart = abandonGuestBag($this);

    expect(app(SendAbandonedCartReminders::class)())->toBe(1)
        ->and(app(SendAbandonedCartReminders::class)())->toBe(0);
    Notification::assertSentTo(new AnonymousNotifiable, AbandonedCartNotification::class, function ($n, $c, $to) use ($cart) {
        return $to->routes['mail'] === 'guest@example.test' && $n->cartUuid === $cart->uuid && $n->lines[0]['quantity'] === 2 && str_contains($n->total, '3,000');
    });
    expect($cart->fresh()->reminder_sent_at)->not->toBeNull();
});

it('skips recent bags, bags without an email and very old bags', function () {
    Notification::fake();
    app(Settings::class)->set('marketing.abandoned_cart_enabled', true);
    $cart = abandonGuestBag($this);

    $cart->forceFill(['last_activity_at' => now()->subMinutes(30)])->save();
    expect(app(SendAbandonedCartReminders::class)())->toBe(0);

    $cart->forceFill(['last_activity_at' => now()->subDays(9)])->save();
    expect(app(SendAbandonedCartReminders::class)())->toBe(0);

    $cart->forceFill(['last_activity_at' => now()->subHours(5), 'email' => null])->save();
    expect(app(SendAbandonedCartReminders::class)())->toBe(0);
    Notification::assertNothingSent();
});

it('reopens a guest bag from the signed email link', function () {
    $cart = abandonGuestBag($this);
    $link = URL::temporarySignedRoute('v1.cart.restore', now()->addDay(), ['cart' => $cart->uuid]);

    $this->get($link)->assertRedirect(rtrim(config('commerce.frontend_url'), '/').'/cart')->assertCookie('cart_token', $cart->token, false);
    $this->get(route('v1.cart.restore', ['cart' => $cart->uuid]))->assertForbidden(); // unsigned
});

it('shows marketing staff abandoned bags and recoveries', function () {
    app(Settings::class)->set('marketing.abandoned_cart_enabled', true);
    abandonGuestBag($this);

    $this->actingAs(User::factory()->staff('marketing-manager')->create());
    $this->getJson('/api/v1/admin/marketing/abandoned-carts')->assertOk()
        ->assertJsonPath('data.enabled', true)
        ->assertJsonPath('data.carts.0.email', 'guest@example.test')
        ->assertJsonPath('data.carts.0.value.amount', 300_000);
});

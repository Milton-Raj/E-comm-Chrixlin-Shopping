<?php

use App\Domain\Identity\PermissionCatalog;
use App\Http\Middleware\EnsureAdminAccess;
use App\Models\Order;
use App\Models\User;
use App\Notifications\NewOrderNotification;
use Illuminate\Notifications\DatabaseNotification;

it('alerts every active staff member who can see orders when an order is paid', function () {
    $manager = User::factory()->staff('order-manager')->create();
    $owner = User::factory()->staff(PermissionCatalog::SUPER_ADMIN)->create();
    $content = User::factory()->staff('content-manager')->create();
    $inactive = User::factory()->staff('order-manager')->create(['is_active' => false]);

    $order = paidOrder($this, makeProduct(price: 118_000), qty: 2);

    foreach ([$manager, $owner] as $user) {
        $alert = $user->notifications()->sole();
        expect($alert->type)->toBe('order_paid')
            ->and($alert->data['order_number'])->toBe($order->order_number)
            ->and($alert->data['item_count'])->toBe(2)
            ->and($alert->data['total']['amount'])->toBe($order->grand_total)
            ->and($alert->data)->not->toHaveKeys(['email', 'phone', 'line1']);
    }
    expect($content->notifications()->count())->toBe(0)
        ->and($inactive->notifications()->count())->toBe(0)
        ->and($order->user->notifications()->count())->toBe(0);
});

it('does not alert twice when the same payment is confirmed again', function () {
    $manager = User::factory()->staff('order-manager')->create();
    $order = paidOrder($this, makeProduct());
    $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson("/api/v1/orders/{$order->order_number}/payment/confirm", ['outcome' => 'success']);

    expect($manager->notifications()->count())->toBe(1);
});

it('lists, reads and clears only the signed-in staff member\'s own alerts', function () {
    $manager = User::factory()->staff('order-manager')->create();
    $other = User::factory()->staff('order-manager')->create();
    paidOrder($this, makeProduct());
    paidOrder($this, makeProduct());

    $this->actingAs($manager);
    $list = $this->getJson('/api/v1/admin/notifications')->assertOk()->assertJsonPath('data.unread_count', 2)->assertJsonCount(2, 'data.items');
    $first = $list->json('data.items.0.id');

    $this->postJson("/api/v1/admin/notifications/{$first}/read")->assertOk()->assertJsonPath('data.unread_count', 1);
    $this->postJson('/api/v1/admin/notifications/'.$other->notifications()->first()->id.'/read')->assertNotFound();
    $this->postJson('/api/v1/admin/notifications/read-all')->assertOk()->assertJsonPath('data.unread_count', 0);

    expect($other->unreadNotifications()->count())->toBe(2)
        ->and(DatabaseNotification::query()->whereNull('read_at')->count())->toBe(2);
});

it('polling the bell never keeps an idle admin session alive', function () {
    $manager = User::factory()->staff('order-manager')->create();
    $before = now()->subMinutes(10)->timestamp;

    $this->actingAs($manager)->withSession([EnsureAdminAccess::LAST_ACTIVITY_KEY => $before])
        ->getJson('/api/v1/admin/notifications')->assertOk();
    expect(session(EnsureAdminAccess::LAST_ACTIVITY_KEY))->toBe($before);

    $this->getJson('/api/v1/admin/orders')->assertOk();
    expect(session(EnsureAdminAccess::LAST_ACTIVITY_KEY))->toBeGreaterThan($before);
});

it('still signs out an idle admin whose only traffic is the bell', function () {
    $manager = User::factory()->staff('order-manager')->create();
    $stale = now()->subMinutes((int) config('commerce.security.admin_idle_minutes') + 1)->timestamp;

    expect($this->actingAs($manager)->withSession([EnsureAdminAccess::LAST_ACTIVITY_KEY => $stale])
        ->getJson('/api/v1/admin/notifications'))->toBeApiError(401);
});

it('stores a foreign charge on the alert', function () {
    $alert = (new NewOrderNotification(new Order([
        'order_number' => 'ORD-2026-000001', 'currency' => 'INR', 'grand_total' => 486_000,
        'presentment_currency' => 'USD', 'presentment_total' => 5_832, 'email' => 'a@example.test',
    ])))->toDatabase(new User);

    expect($alert['charged'])->toMatchArray(['amount' => 5_832, 'currency' => 'USD'])
        ->and($alert['customer'])->toBe('a@example.test');
});

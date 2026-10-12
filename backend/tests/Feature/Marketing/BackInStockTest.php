<?php

use App\Domain\Inventory\InventoryService;
use App\Domain\Marketing\Actions\SendBackInStockAlerts;
use App\Models\StockAlert;
use App\Models\User;
use App\Notifications\BackInStockNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

it('records one request per email for a sold-out variant', function () {
    $product = makeProduct(stock: 0);
    $variant = variantOf($product);

    $this->postJson("/api/v1/products/{$product->slug}/stock-alerts", ['variant_uuid' => $variant->uuid, 'email' => 'Asha@Example.test'])->assertCreated();
    $this->postJson("/api/v1/products/{$product->slug}/stock-alerts", ['variant_uuid' => $variant->uuid, 'email' => 'asha@example.test'])->assertCreated();

    expect(StockAlert::query()->count())->toBe(1)->and(StockAlert::query()->value('email'))->toBe('asha@example.test');
});

it('refuses requests for items that are in stock and validates input', function () {
    $product = makeProduct(stock: 4);

    expect($this->postJson("/api/v1/products/{$product->slug}/stock-alerts", ['variant_uuid' => variantOf($product)->uuid, 'email' => 'a@example.test']))->toBeApiError(409, 'in_stock');
    $this->postJson("/api/v1/products/{$product->slug}/stock-alerts", ['variant_uuid' => variantOf($product)->uuid, 'email' => 'nope'])->assertStatus(422);
});

it('emails each waiting shopper once when stock returns', function () {
    Notification::fake();
    $product = makeProduct(stock: 0);
    $variant = variantOf($product);
    foreach (['a@example.test', 'b@example.test'] as $email) {
        $this->postJson("/api/v1/products/{$product->slug}/stock-alerts", ['variant_uuid' => $variant->uuid, 'email' => $email])->assertCreated();
    }

    expect((new SendBackInStockAlerts)())->toBe(0); // still sold out
    app(InventoryService::class)->adjust($variant->fresh(), 'restock', 5, 'Restock', User::factory()->staff('administrator')->create());

    expect((new SendBackInStockAlerts)())->toBe(2)
        ->and((new SendBackInStockAlerts)())->toBe(0); // never twice
    Notification::assertSentTo(new AnonymousNotifiable, BackInStockNotification::class, fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'a@example.test');
    Notification::assertSentTimes(BackInStockNotification::class, 2);
});

it('shows marketing staff which items shoppers are waiting for', function () {
    $product = makeProduct(stock: 0);
    $this->postJson("/api/v1/products/{$product->slug}/stock-alerts", ['variant_uuid' => variantOf($product)->uuid, 'email' => 'a@example.test']);

    $this->actingAs(User::factory()->staff('marketing-manager')->create());
    $this->getJson('/api/v1/admin/marketing/stock-alerts')->assertOk()
        ->assertJsonPath('data.waiting.0.product.name', $product->name)
        ->assertJsonPath('data.waiting.0.waiting', 1);

    $this->actingAs(User::factory()->staff('order-manager')->create());
    $this->getJson('/api/v1/admin/marketing/stock-alerts')->assertForbidden();
});

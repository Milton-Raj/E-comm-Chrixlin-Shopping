<?php

use App\Domain\Inventory\InventoryService;
use App\Models\DigitalFile;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\TaxClass;
use App\Models\User;
use Database\Seeders\StoreSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/*
| Feature and Security tests hit MySQL (ecom_testing) inside transactions.
| Unit tests boot the framework (for config) but do not touch the database.
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Security');

pest()->extend(TestCase::class)->in('Unit');

/*
| Assert the standard error envelope (API.md §1.2).
*/
expect()->extend('toBeApiError', function (int $status, ?string $code = null) {
    /** @var TestResponse $response */
    $response = $this->value;
    $response->assertStatus($status)
        ->assertJsonPath('success', false)
        ->assertJsonStructure(['success', 'message', 'errors', 'meta' => ['request_id']]);

    if ($code !== null) {
        $response->assertJsonPath('errors.code', $code);
    }

    return $this;
});

/*
| Commerce test helpers.
*/
function makeProduct(array $overrides = [], int $stock = 10, int $price = 100_000, string $type = 'physical'): Product
{
    (new StoreSetupSeeder)->run();
    static $n = 0;
    $n++;
    $product = Product::create(array_merge([
        'product_type' => $type,
        'status' => 'active',
        'name' => "Test Product {$n}",
        'slug' => "test-product-{$n}-".Str::random(4),
        'currency' => 'INR',
        'tax_class_id' => TaxClass::default()?->id,
        'published_at' => now()->subDay(),
    ], $overrides));
    $variant = $product->variants()->create(['sku' => 'SKU-'.Str::upper(Str::random(8)), 'price' => $price, 'track_inventory' => $type === 'physical', 'is_default' => true]);
    if ($type === 'physical') {
        $variant->inventory()->create(['on_hand' => 0, 'reserved' => 0]);
        if ($stock > 0) {
            app(InventoryService::class)->adjust($variant, 'opening', $stock);
        }
    } else {
        $product->digital()->create(['download_limit' => 3, 'format' => 'PDF']);
        Storage::disk('local')->put("digital/{$product->uuid}/file.pdf", '%PDF-1.4 test');
        DigitalFile::create(['product_id' => $product->id, 'disk' => 'local', 'path' => "digital/{$product->uuid}/file.pdf", 'original_name' => 'file.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 13, 'checksum_sha256' => str_repeat('a', 64)]);
    }
    $product->refreshPriceRange();

    return $product->fresh(['variants.inventory']);
}

function variantOf(Product $product): ProductVariant
{
    return $product->variants()->with('inventory')->firstOrFail();
}

/** Places and pays (test gateway) an order for a fresh customer; leaves that customer signed in. */
function paidOrder($test, Product $product, int $qty = 1): Order
{
    $customer = User::factory()->customer()->create();
    $test->actingAs($customer);
    $test->postJson('/api/v1/cart/items', ['variant_uuid' => variantOf($product)->uuid, 'quantity' => $qty]);
    $test->putJson('/api/v1/checkout/contact', ['email' => $customer->email]);
    $test->putJson('/api/v1/checkout/address', ['name' => 'C', 'line1' => 'x', 'city' => 'Chennai', 'state_code' => 'TN', 'postal_code' => '600001', 'country_code' => 'IN']);
    $number = $test->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/checkout/place-order', ['gateway' => 'test'])->json('data.order_number');
    $test->withHeader('Idempotency-Key', (string) Str::uuid())->postJson("/api/v1/orders/{$number}/payment/confirm", ['outcome' => 'success']);

    return Order::query()->where('order_number', $number)->firstOrFail();
}

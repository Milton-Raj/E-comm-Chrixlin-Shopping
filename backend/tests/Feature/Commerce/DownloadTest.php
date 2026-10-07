<?php

use App\Models\DigitalEntitlement;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

/** Buys a digital product as a signed-in customer and returns [user, entitlement]. */
function buyDigital($test): array
{
    Notification::fake();
    $user = User::factory()->customer()->create();
    $product = makeProduct(type: 'digital');
    $test->actingAs($user);
    $test->postJson('/api/v1/cart/items', ['variant_uuid' => variantOf($product)->uuid, 'quantity' => 1]);
    $test->putJson('/api/v1/checkout/contact', ['email' => $user->email]);
    $number = $test->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/checkout/place-order', ['gateway' => 'test'])->json('data.order_number');
    $test->withHeader('Idempotency-Key', (string) Str::uuid())->postJson("/api/v1/orders/{$number}/payment/confirm", ['outcome' => 'success'])->assertOk();

    return [$user, DigitalEntitlement::query()->with('product.files')->firstOrFail()];
}

it('lists downloads and streams a file through a single-use link', function () {
    [, $entitlement] = buyDigital($this);
    $file = $entitlement->product->files->first();

    $this->getJson('/api/v1/account/downloads')->assertOk()->assertJsonPath('data.0.files.0.uuid', $file->uuid)->assertJsonMissingPath('data.0.files.0.path');

    $url = $this->postJson("/api/v1/account/downloads/{$entitlement->uuid}/files/{$file->uuid}/link")->assertOk()->json('data.url');
    $path = parse_url($url, PHP_URL_PATH);

    $this->get($path)->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->get($path)->assertNotFound(); // single use

    expect($entitlement->fresh()->downloads_used)->toBe(1)->and($entitlement->fresh()->status)->toBe('downloaded');
});

it('enforces the download limit, expiry and revocation', function () {
    [, $entitlement] = buyDigital($this);
    $file = $entitlement->product->files->first();
    $link = fn () => $this->postJson("/api/v1/account/downloads/{$entitlement->uuid}/files/{$file->uuid}/link");

    $entitlement->forceFill(['downloads_used' => 3])->save(); // limit 3
    expect($link())->toBeApiError(403, 'download_limit_reached');

    $entitlement->forceFill(['downloads_used' => 0, 'expires_at' => now()->subMinute()])->save();
    expect($link())->toBeApiError(403, 'entitlement_expired');

    $entitlement->forceFill(['expires_at' => null, 'status' => 'revoked'])->save();
    expect($link())->toBeApiError(403, 'entitlement_revoked');
});

it('hides downloads from other customers and rejects expired tokens', function () {
    [, $entitlement] = buyDigital($this);
    $file = $entitlement->product->files->first();
    $url = $this->postJson("/api/v1/account/downloads/{$entitlement->uuid}/files/{$file->uuid}/link")->json('data.url');

    $this->actingAs(User::factory()->customer()->create());
    $this->postJson("/api/v1/account/downloads/{$entitlement->uuid}/files/{$file->uuid}/link")->assertNotFound();

    $this->travel(6)->minutes();
    $this->get(parse_url($url, PHP_URL_PATH))->assertNotFound();
    expect(Order::query()->count())->toBe(1);
});

it('never serves digital files from a public path', function () {
    [, $entitlement] = buyDigital($this);
    $file = $entitlement->product->files->first();

    $this->get('/storage/'.$file->path)->assertNotFound();
});

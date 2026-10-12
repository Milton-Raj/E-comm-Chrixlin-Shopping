<?php

use App\Models\Category;
use App\Models\User;

function editorPayload($test, $product, array $changes): array
{
    $payload = $test->getJson("/api/v1/admin/products/{$product->uuid}")->assertOk()->json('data');
    foreach (['tax_class', 'category', 'brand'] as $key) {
        $payload[$key] = $payload[$key]['uuid'] ?? null;
    }
    $payload['slug'] = strtolower($payload['slug']);
    $payload['pairs_with'] = collect($payload['pairs_with'])->pluck('uuid')->all();

    return array_merge($payload, $changes);
}

it('saves hand-picked pairings in order and shows them first on the product page', function () {
    $category = Category::create(['name' => 'Candles', 'slug' => 'candles', 'is_active' => true]);
    $main = makeProduct(['category_id' => $category->id]);
    [$a, $b] = [makeProduct(), makeProduct()];
    $sameCategory = makeProduct(['category_id' => $category->id]);
    $soldOut = makeProduct(stock: 0);

    $this->actingAs(User::factory()->staff('product-manager')->create());
    $this->putJson("/api/v1/admin/products/{$main->uuid}", editorPayload($this, $main, ['pairs_with' => [$b->uuid, $soldOut->uuid, $a->uuid, $main->uuid]]))->assertOk()
        ->assertJsonPath('data.pairs_with.0.uuid', $b->uuid)
        ->assertJsonCount(3, 'data.pairs_with'); // never pairs a product with itself

    $slugs = collect($this->getJson("/api/v1/products/{$main->slug}/related")->assertOk()->json('data'))->pluck('slug')->all();

    expect(array_slice($slugs, 0, 3))->toBe([$b->slug, $a->slug, $sameCategory->slug]) // sold-out pick skipped, then same category
        ->and($slugs)->not->toContain($main->slug)->not->toContain($soldOut->slug);
});

it('suggests pairings for the bag without repeating what is already in it', function () {
    $candle = makeProduct();
    [$holder, $matches] = [makeProduct(), makeProduct()];
    $candle->pairsWith()->sync([$holder->id => ['type' => 'cross_sell', 'sort_order' => 0], $matches->id => ['type' => 'cross_sell', 'sort_order' => 1]]);

    $this->postJson('/api/v1/cart/items', ['variant_uuid' => variantOf($candle)->uuid, 'quantity' => 1])->assertCreated();
    $this->postJson('/api/v1/cart/items', ['variant_uuid' => variantOf($matches)->uuid, 'quantity' => 1])->assertCreated();

    $slugs = collect($this->getJson('/api/v1/cart/suggestions')->assertOk()->json('data'))->pluck('slug');
    expect($slugs->first())->toBe($holder->slug)
        ->and($slugs)->not->toContain($candle->slug)->not->toContain($matches->slug);
});

it('returns no bag suggestions for an empty bag', function () {
    $this->getJson('/api/v1/cart/suggestions')->assertOk()->assertJsonCount(0, 'data');
});

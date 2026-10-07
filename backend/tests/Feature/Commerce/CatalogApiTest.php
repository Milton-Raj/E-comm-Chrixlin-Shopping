<?php

it('lists only visible products with public fields', function () {
    $visible = makeProduct(['name' => 'Visible Bag']);
    makeProduct(['name' => 'Draft Bag', 'status' => 'draft']);

    $response = $this->getJson('/api/v1/products')->assertOk();

    expect(collect($response->json('data'))->pluck('name')->all())->toContain('Visible Bag')->not->toContain('Draft Bag');
    $response->assertJsonPath('data.0.price.currency', 'INR')
        ->assertJsonMissingPath('data.0.id')
        ->assertJsonMissingPath('data.0.stock_on_hand');
});

it('filters by type, searches and sorts by price', function () {
    makeProduct(['name' => 'Cheap Candle'], price: 10_000);
    makeProduct(['name' => 'Dear Watch'], price: 900_000);
    makeProduct(['name' => 'Fine eBook'], price: 50_000, type: 'digital');

    $this->getJson('/api/v1/products?type=digital')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Fine eBook');
    $this->getJson('/api/v1/products?q=watch')->assertJsonPath('data.0.name', 'Dear Watch');
    $this->getJson('/api/v1/products?sort=price_asc')->assertJsonPath('data.0.name', 'Cheap Candle');
    $this->getJson('/api/v1/products?sort=drop_table')->assertStatus(422);
});

it('shows product detail with variants and stock status but never stock counts', function () {
    $product = makeProduct(stock: 3);

    $this->getJson("/api/v1/products/{$product->slug}")->assertOk()
        ->assertJsonPath('data.stock_status', 'low_stock')
        ->assertJsonPath('data.variants.0.available', true)
        ->assertJsonMissingPath('data.variants.0.on_hand');

    $this->getJson('/api/v1/products/does-not-exist')->assertNotFound();
});

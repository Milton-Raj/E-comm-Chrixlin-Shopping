<?php

use App\Support\Settings\Settings;

it('reports liveness publicly without internals', function () {
    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertJsonPath('data.status', 'ok')
        ->assertJsonMissingPath('data.database');
});

it('reports details with the health token', function () {
    $this->withHeader('X-Health-Token', 'test-health-token')
        ->getJson('/api/v1/health')
        ->assertOk()
        ->assertJsonPath('data.database', 'ok')
        ->assertJsonStructure(['data' => ['queue_pending', 'queue_lag_seconds', 'scheduler_last_run_seconds_ago']]);
});

it('exposes public store settings with admin overrides', function () {
    app(Settings::class)->set('store.name', 'Maison');

    $this->getJson('/api/v1/settings/public')
        ->assertOk()
        ->assertJsonPath('data.store.name', 'Maison')
        ->assertJsonPath('data.store.currency', 'INR')
        ->assertJsonPath('data.features.guest_checkout', true);
});

<?php

use App\Models\AuditLog;
use App\Models\HeroSlide;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->editor = User::factory()->staff('content-manager')->create();
});

function slidePayload(array $overrides = []): array
{
    return array_merge([
        'image' => UploadedFile::fake()->image('candles.jpg', 1600, 900),
        'image_alt' => 'Amber candle on a stone tray',
        'eyebrow' => 'Hand-poured',
        'title' => 'Light that lingers',
        'body' => 'Soy candles in amber glass.',
        'cta_label' => 'Shop candles',
        'cta_url' => '/shop?q=candle',
        'focal_point' => '50% 80%',
    ], $overrides);
}

it('creates, updates and orders slides shown on the storefront', function () {
    $first = $this->actingAs($this->editor)->post('/api/v1/admin/hero-slides', slidePayload(), ['Accept' => 'application/json'])
        ->assertCreated()->json('data');
    Storage::disk('public')->assertExists(HeroSlide::first()->image_path);

    $second = $this->actingAs($this->editor)->post('/api/v1/admin/hero-slides', slidePayload(['title' => 'Cast in Jesmonite']), ['Accept' => 'application/json'])
        ->assertCreated()->json('data');

    $this->actingAs($this->editor)->postJson('/api/v1/admin/hero-slides/reorder', ['order' => [$second['uuid'], $first['uuid']]])->assertOk();

    $this->getJson('/api/v1/hero-slides')->assertOk()
        ->assertJsonPath('data.0.title', 'Cast in Jesmonite')
        ->assertJsonPath('data.1.cta', ['label' => 'Shop candles', 'url' => '/shop?q=candle'])
        ->assertJsonPath('data.1.focal_point', '50% 80%');

    $oldPath = HeroSlide::where('uuid', $first['uuid'])->value('image_path');
    $this->actingAs($this->editor)->post("/api/v1/admin/hero-slides/{$first['uuid']}", ['title' => 'Evening glow', 'is_active' => '0', 'image' => UploadedFile::fake()->image('new.jpg', 2000, 1200)], ['Accept' => 'application/json'])
        ->assertOk()->assertJsonPath('data.is_active', false);
    Storage::disk('public')->assertMissing($oldPath);

    $this->getJson('/api/v1/hero-slides')->assertOk()->assertJsonCount(1, 'data');
    expect(AuditLog::where('action', 'like', 'hero_slide.%')->count())->toBe(4);
});

it('deletes a slide with its image', function () {
    $uuid = $this->actingAs($this->editor)->post('/api/v1/admin/hero-slides', slidePayload(), ['Accept' => 'application/json'])->json('data.uuid');
    $path = HeroSlide::first()->image_path;

    $this->actingAs($this->editor)->deleteJson("/api/v1/admin/hero-slides/{$uuid}")->assertOk();

    expect(HeroSlide::count())->toBe(0);
    Storage::disk('public')->assertMissing($path);
});

it('rejects unsafe links, small images and missing images', function () {
    $this->actingAs($this->editor)->post('/api/v1/admin/hero-slides', slidePayload(['cta_url' => 'javascript:alert(1)']), ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonValidationErrors('cta_url');
    $this->actingAs($this->editor)->post('/api/v1/admin/hero-slides', slidePayload(['cta_url' => '//evil.example']), ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonValidationErrors('cta_url');
    $this->actingAs($this->editor)->post('/api/v1/admin/hero-slides', slidePayload(['image' => UploadedFile::fake()->image('tiny.jpg', 400, 300)]), ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonValidationErrors('image');
    $this->actingAs($this->editor)->post('/api/v1/admin/hero-slides', slidePayload(['image' => null]), ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonValidationErrors('image');
});

it('requires content.manage', function () {
    $staff = User::factory()->staff('order-manager')->create();

    expect($this->actingAs($staff)->getJson('/api/v1/admin/hero-slides'))->toBeApiError(403);
    expect($this->actingAs($staff)->post('/api/v1/admin/hero-slides', slidePayload(), ['Accept' => 'application/json']))->toBeApiError(403);
});

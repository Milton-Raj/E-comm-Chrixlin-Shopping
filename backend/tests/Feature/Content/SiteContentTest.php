<?php

use App\Models\AuditLog;
use App\Models\HeroSlide;
use App\Models\User;
use Database\Seeders\HeroSlidesSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->editor = User::factory()->staff('content-manager')->create();
});

it('saves whitelisted site text and serves it to the storefront', function () {
    $this->getJson('/api/v1/site-content')->assertOk()->assertJsonPath('data.texts', []);

    $this->actingAs($this->editor)->putJson('/api/v1/admin/site-content', ['texts' => [
        'announce.preview' => 'Hand-poured in Chennai', 'home.editorialTitle' => '  Slow craft  ', 'brand.tagline' => '', 'hacker.key' => 'nope',
    ]])->assertOk()->assertJsonPath('data.texts', ['announce.preview' => 'Hand-poured in Chennai', 'home.editorialTitle' => 'Slow craft']);

    $this->getJson('/api/v1/site-content')->assertOk()
        ->assertJsonPath('data.texts', ['announce.preview' => 'Hand-poured in Chennai', 'home.editorialTitle' => 'Slow craft']);
    expect(AuditLog::where('action', 'content.site_text_updated')->exists())->toBeTrue();

    // Blank resets a field to the default; other fields are kept.
    $this->actingAs($this->editor)->putJson('/api/v1/admin/site-content', ['texts' => ['announce.preview' => null]])
        ->assertOk()->assertJsonPath('data.texts', ['home.editorialTitle' => 'Slow craft']);
});

it('validates lengths and requires content.manage', function () {
    $this->actingAs($this->editor)->putJson('/api/v1/admin/site-content', ['texts' => ['announce.preview' => str_repeat('a', 121)]])
        ->assertUnprocessable();

    $staff = User::factory()->staff('order-manager')->create();
    $this->flushSession();
    expect($this->actingAs($staff)->getJson('/api/v1/admin/site-content'))->toBeApiError(403);
});

it('replaces and resets the homepage story photo', function () {
    $url = $this->actingAs($this->editor)->post('/api/v1/admin/site-content/editorial-image', ['image' => UploadedFile::fake()->image('studio.jpg', 1600, 1200)], ['Accept' => 'application/json'])
        ->assertOk()->json('data.editorial_image');
    expect($url)->toContain('/storage/content/');

    $this->actingAs($this->editor)->postJson('/api/v1/admin/site-content/editorial-image', [])->assertOk()->assertJsonPath('data.editorial_image', null);
    expect(Storage::disk('public')->allFiles('content'))->toBe([]);
});

it('seeds the launch hero slides once', function () {
    (new HeroSlidesSeeder)->run();
    expect(HeroSlide::count())->toBe(4)->and(HeroSlide::orderBy('sort_order')->value('title'))->toBe('Good enough to eat. Made to light.');
    Storage::disk('public')->assertExists('hero/berry-cake.jpg');

    HeroSlide::query()->delete();
    (new HeroSlidesSeeder)->run();
    expect(HeroSlide::count())->toBe(0);
});

<?php

use App\Models\Product;
use App\Models\User;
use App\Support\Media\YouTube;
use Database\Seeders\StoreSetupSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    (new StoreSetupSeeder)->run();
    $this->admin = User::factory()->staff('administrator')->create();
});

it('reads the video id from every common YouTube link and rejects anything else', function (string $url, ?string $id) {
    expect(YouTube::videoId($url))->toBe($id);
})->with([
    ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
    ['https://youtube.com/watch?feature=share&v=dQw4w9WgXcQ&t=10', 'dQw4w9WgXcQ'],
    ['https://m.youtube.com/watch?v=dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
    ['https://youtu.be/dQw4w9WgXcQ?si=abc', 'dQw4w9WgXcQ'],
    ['https://www.youtube.com/shorts/dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
    ['https://www.youtube.com/embed/dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
    ['  https://www.youtube.com/live/dQw4w9WgXcQ  ', 'dQw4w9WgXcQ'],
    ['https://vimeo.com/123456', null],
    ['https://evil.example/watch?v=dQw4w9WgXcQ', null],
    ['https://youtube.com.evil.example/watch?v=dQw4w9WgXcQ', null],
    ['javascript:alert(1)//youtube.com/watch?v=dQw4w9WgXcQ', null],
    ['https://www.youtube.com/watch?v=<script>x', null],
    ['https://www.youtube.com/@chrixlin', null],
]);

it('adds an optional YouTube video that shows after the images on the product page', function () {
    $product = makeProduct(['name' => 'Rose Candle', 'slug' => 'rose-candle']);
    $product->media()->create(['disk' => 'public', 'path' => 'demo/rose.jpg', 'sort_order' => 0]);

    $this->getJson('/api/v1/products/rose-candle')->assertOk()->assertJsonPath('data.videos', []);

    $this->actingAs($this->admin)->postJson("/api/v1/admin/products/{$product->uuid}/videos", ['url' => 'https://youtu.be/dQw4w9WgXcQ'])
        ->assertCreated()
        ->assertJsonPath('data.videos.0.youtube_id', 'dQw4w9WgXcQ')
        ->assertJsonCount(1, 'data.media'); // images unchanged

    $this->getJson('/api/v1/products/rose-candle')->assertOk()
        ->assertJsonCount(1, 'data.images')
        ->assertJsonPath('data.videos.0.youtube_id', 'dQw4w9WgXcQ')
        ->assertJsonPath('data.videos.0.thumbnail', 'https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg');

    // Listings and the cart keep using the first image, never a video.
    expect(Product::query()->find($product->id)->media)->toHaveCount(1);
});

it('rejects links that are not YouTube videos, duplicates and more than three videos', function () {
    $product = makeProduct();
    $url = "/api/v1/admin/products/{$product->uuid}/videos";
    $this->actingAs($this->admin);

    expect($this->postJson($url, ['url' => 'https://vimeo.com/1']))->toBeApiError(422, 'invalid_video');
    $this->postJson($url, ['url' => 'https://youtu.be/aaaaaaaaaaa'])->assertCreated();
    expect($this->postJson($url, ['url' => 'https://www.youtube.com/watch?v=aaaaaaaaaaa']))->toBeApiError(422, 'duplicate_video');
    $this->postJson($url, ['url' => 'https://youtu.be/bbbbbbbbbbb'])->assertCreated();
    $this->postJson($url, ['url' => 'https://youtu.be/ccccccccccc'])->assertCreated();
    expect($this->postJson($url, ['url' => 'https://youtu.be/ddddddddddd']))->toBeApiError(422, 'too_many_videos');
});

it('removes a video without touching the images', function () {
    $product = makeProduct();
    $product->media()->create(['disk' => 'public', 'path' => 'demo/a.jpg', 'sort_order' => 0]);
    $this->actingAs($this->admin);
    $video = $this->postJson("/api/v1/admin/products/{$product->uuid}/videos", ['url' => 'https://youtu.be/dQw4w9WgXcQ'])->json('data.videos.0.uuid');

    $this->deleteJson("/api/v1/admin/products/{$product->uuid}/media/{$video}")->assertOk()
        ->assertJsonCount(0, 'data.videos')->assertJsonCount(1, 'data.media');
});

it('lets only staff who can edit products add videos', function () {
    $product = makeProduct();

    $this->postJson("/api/v1/admin/products/{$product->uuid}/videos", ['url' => 'https://youtu.be/dQw4w9WgXcQ'])->assertUnauthorized();
    $this->actingAs(User::factory()->customer()->create())
        ->postJson("/api/v1/admin/products/{$product->uuid}/videos", ['url' => 'https://youtu.be/dQw4w9WgXcQ'])->assertForbidden();
});

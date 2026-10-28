<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('public'));

it('serves catalog images from the public disk with cache headers', function () {
    Storage::disk('public')->putFileAs('products/abc', UploadedFile::fake()->image('jar.jpg'), 'jar.jpg');

    $response = $this->get('/storage/products/abc/jar.jpg')->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('image/jpeg')
        ->and($response->headers->get('Cache-Control'))->toContain('max-age=604800')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->getCookies())->toBeEmpty();
});

it('returns 404 for missing images', function () {
    $this->get('/storage/products/abc/missing.jpg')->assertNotFound();
});

it('refuses non-image files, dotfiles and path traversal', function (string $path) {
    Storage::disk('public')->put('notes/readme.txt', 'x');
    Storage::disk('public')->put('.hidden/a.jpg', 'x');
    Storage::disk('public')->put('page.html', '<script>alert(1)</script>');

    $this->get('/storage/'.$path)->assertNotFound();
})->with([
    'text file' => 'notes/readme.txt',
    'html file' => 'page.html',
    'dot directory' => '.hidden/a.jpg',
    'traversal' => 'products/../../.env.jpg',
    'empty segment' => 'products//a.jpg',
]);

it('does not accept uploads', function () {
    $this->put('/storage/products/abc/jar.jpg', ['x' => 1])->assertStatus(405);
});

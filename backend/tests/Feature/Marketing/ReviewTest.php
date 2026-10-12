<?php

use App\Models\AuditLog;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('public'));

it('holds a new review for moderation and stores photos re-encoded as WebP', function () {
    $product = makeProduct();
    $this->actingAs(User::factory()->customer()->create(['name' => 'Asha Raman']));

    $this->post("/api/v1/products/{$product->slug}/reviews", [
        'rating' => 5, 'title' => 'Smells divine', 'body' => 'Looks exactly like a real cake. Gifted it to my sister.',
        'photos' => [UploadedFile::fake()->image('cake.jpg', 2400, 1800)],
    ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.status', 'pending');

    $review = Review::query()->with('photos')->firstOrFail();
    expect($review->order_item_id)->toBeNull() // never bought it
        ->and($review->photos)->toHaveCount(1)
        ->and($review->photos[0]->path)->toEndWith('.webp');
    [$w, $h] = getimagesizefromstring(Storage::disk('public')->get($review->photos[0]->path));
    expect(max($w, $h))->toBe(1600);

    $this->getJson("/api/v1/products/{$product->slug}/reviews")->assertOk()->assertJsonCount(0, 'data.reviews'); // not public yet
});

it('marks reviews from paying customers as verified and allows one review per product', function () {
    $product = makeProduct();
    paidOrder($this, $product); // signs in the buyer

    $this->postJson("/api/v1/products/{$product->slug}/reviews", ['rating' => 4, 'body' => 'Lovely scent, burns evenly.'])->assertCreated();
    expect(Review::query()->value('order_item_id'))->not->toBeNull();
    expect($this->postJson("/api/v1/products/{$product->slug}/reviews", ['rating' => 1, 'body' => 'Trying to review twice.']))->toBeApiError(409, 'review_exists');
});

it('requires sign-in and validates reviews', function () {
    $product = makeProduct();
    $this->postJson("/api/v1/products/{$product->slug}/reviews", ['rating' => 5, 'body' => 'Great product overall.'])->assertUnauthorized();

    $this->actingAs(User::factory()->customer()->create());
    $this->post("/api/v1/products/{$product->slug}/reviews", [
        'rating' => 6, 'body' => 'short', 'photos' => [UploadedFile::fake()->create('virus.php', 10, 'text/x-php')],
    ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors(['rating', 'body', 'photos.0']);
});

it('publishes approved reviews, updates the rating and hides the email', function () {
    $product = makeProduct(['rating_avg' => 0, 'rating_count' => 0]);
    foreach ([5, 3] as $rating) {
        $this->actingAs(User::factory()->customer()->create(['name' => 'Meera K Iyer']));
        $this->postJson("/api/v1/products/{$product->slug}/reviews", ['rating' => $rating, 'body' => 'An honest review of this candle.'])->assertCreated();
    }
    $moderator = User::factory()->staff('product-manager')->create();
    $this->actingAs($moderator);
    $uuids = collect($this->getJson('/api/v1/admin/reviews')->assertOk()->assertJsonPath('meta.pending', 2)->json('data'))->pluck('uuid');

    $this->postJson("/api/v1/admin/reviews/{$uuids[0]}/moderate", ['status' => 'approved'])->assertOk();
    $this->postJson("/api/v1/admin/reviews/{$uuids[1]}/moderate", ['status' => 'rejected'])->assertOk();

    $public = $this->getJson("/api/v1/products/{$product->slug}/reviews")->assertOk();
    $public->assertJsonCount(1, 'data.reviews')->assertJsonPath('data.reviews.0.author', 'Meera I.')->assertJsonPath('data.summary.count', 1);
    expect($public->getContent())->not->toContain('@example');
    expect($product->fresh()->rating_count)->toBe(1)
        ->and(AuditLog::query()->where('action', 'review.approved')->exists())->toBeTrue();
});

it('keeps moderation to staff with review permission', function () {
    $this->actingAs(User::factory()->staff('order-manager')->create());
    $this->getJson('/api/v1/admin/reviews')->assertForbidden();
});

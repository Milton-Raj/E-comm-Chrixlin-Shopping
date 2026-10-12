<?php

use App\Domain\Marketing\MarketingSettings;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\OrderStateMachine;
use App\Domain\Reviews\Actions\SendReviewRequests;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Notifications\ReviewRequestNotification;
use App\Support\Settings\Settings;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

function deliveredOrder($test, Product $product): Order
{
    $order = paidOrder($test, $product);
    app(OrderStateMachine::class)->advance($order->fresh(), OrderStatus::Delivered);

    return $order->fresh();
}

/** @param  array<string, mixed>  $marketing */
function reviewSettingsPayload(array $marketing): array
{
    return ['store' => ['name' => 'Chrixlin', 'state_code' => 'TN'], 'marketing' => $marketing + [
        'low_stock_count' => 5, 'whatsapp_number' => null, 'gift_wrap_enabled' => false, 'gift_wrap_price' => 0,
        'abandoned_cart_enabled' => false, 'abandoned_cart_delay_hours' => 3,
    ]];
}

beforeEach(fn () => Notification::fake());

it('emails once, a few days after delivery, linking to each product review form', function () {
    $product = makeProduct();
    $order = deliveredOrder($this, $product);

    $this->travel(2)->days();
    expect(app(SendReviewRequests::class)())->toBe(0); // too early (default 4 days)

    $this->travel(3)->days();
    expect(app(SendReviewRequests::class)())->toBe(1)
        ->and(app(SendReviewRequests::class)())->toBe(0); // never twice

    Notification::assertSentOnDemand(ReviewRequestNotification::class, function (ReviewRequestNotification $n, array $channels, AnonymousNotifiable $to) use ($order, $product) {
        $html = (string) $n->toMail($to)->render();

        return $to->routes['mail'] === $order->email && $n->orderNumber === $order->order_number
            && str_contains($html, "/product/{$product->slug}#write-review");
    });
    expect($order->fresh()->review_requested_at)->not->toBeNull();
});

it('skips products already reviewed and honours the owner switch', function () {
    $product = makeProduct();
    $order = deliveredOrder($this, $product);
    Review::create(['product_id' => $product->id, 'user_id' => $order->user_id, 'rating' => 5, 'body' => 'Already loved it.', 'status' => 'pending']);
    $this->travel(5)->days();

    expect(app(SendReviewRequests::class)())->toBe(0);
    Notification::assertSentOnDemandTimes(ReviewRequestNotification::class, 0);
    expect($order->fresh()->review_requested_at)->not->toBeNull(); // claimed, nothing to ask

    $other = deliveredOrder($this, makeProduct());
    app(Settings::class)->set('marketing.review_request_enabled', false);
    $this->travel(5)->days();
    expect(app(SendReviewRequests::class)())->toBe(0)
        ->and($other->fresh()->review_requested_at)->toBeNull();
});

it('leaves orders delivered long before the feature ran alone', function () {
    $order = deliveredOrder($this, makeProduct());
    $this->travel(60)->days();

    expect(app(SendReviewRequests::class)())->toBe(0)
        ->and($order->fresh()->review_requested_at)->toBeNull();
});

it('offers "Write a review" on delivered orders until the piece is reviewed', function () {
    $product = makeProduct();
    $order = paidOrder($this, $product);
    $this->getJson("/api/v1/orders/{$order->order_number}")->assertOk()->assertJsonPath('data.items.0.can_review', false);

    app(OrderStateMachine::class)->advance($order->fresh(), OrderStatus::Delivered);
    $this->getJson("/api/v1/orders/{$order->order_number}")->assertJsonPath('data.items.0.can_review', true);

    $this->postJson("/api/v1/products/{$product->slug}/reviews", ['rating' => 5, 'body' => 'Beautiful and well packed.'])->assertCreated();
    $this->getJson("/api/v1/orders/{$order->order_number}")->assertJsonPath('data.items.0.can_review', false);
});

it('lets the owner change the review request settings', function () {
    $this->actingAs(User::factory()->staff('administrator')->create());
    $this->getJson('/api/v1/admin/settings')->assertOk()
        ->assertJsonPath('data.marketing.review_request_enabled', true)
        ->assertJsonPath('data.marketing.review_request_delay_days', 4);

    $this->putJson('/api/v1/admin/settings', reviewSettingsPayload(['review_request_enabled' => false, 'review_request_delay_days' => 7]))->assertOk();
    $this->putJson('/api/v1/admin/settings', reviewSettingsPayload(['review_request_delay_days' => 99]))
        ->assertStatus(422)->assertJsonValidationErrors(['marketing.review_request_delay_days']);

    $settings = app(MarketingSettings::class);
    expect($settings->reviewRequestEnabled())->toBeFalse()->and($settings->reviewRequestDelayDays())->toBe(7);
});

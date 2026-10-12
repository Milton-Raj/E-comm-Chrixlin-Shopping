<?php

namespace App\Domain\Reviews;

use App\Domain\Payments\Enums\PaymentStatus;
use App\Exceptions\ApiException;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Images\ImageSanitizer;
use App\Support\Storefront\StorefrontCache;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Customer reviews (PRD §37): one per customer per product, held for moderation,
 * "Verified purchase" only when the customer paid for the product.
 */
class ReviewService
{
    public function __construct(private readonly ImageSanitizer $images) {}

    /**
     * @param  array{rating: int, title?: string|null, body: string}  $data
     * @param  list<UploadedFile>  $photos
     */
    public function submit(Product $product, User $user, array $data, array $photos): Review
    {
        if (Review::withTrashed()->where('product_id', $product->id)->where('user_id', $user->id)->exists()) {
            throw new ApiException('You have already reviewed this product.', 409, 'review_exists');
        }

        // Encode before the transaction so a bad file never leaves a half-saved review.
        $encoded = array_map(fn (UploadedFile $f) => $this->images->toWebp($f), $photos);
        $stored = [];

        try {
            return DB::transaction(function () use ($product, $user, $data, $encoded, &$stored) {
                $review = Review::create([
                    'product_id' => $product->id, 'user_id' => $user->id, 'order_item_id' => $this->purchasedItemId($product, $user),
                    'rating' => $data['rating'], 'title' => $data['title'] ?? null, 'body' => $data['body'], 'status' => 'pending',
                ]);
                foreach ($encoded as $i => $bytes) {
                    $path = "reviews/{$review->uuid}/".Str::random(32).'.webp';
                    Storage::disk('public')->put($path, $bytes);
                    $stored[] = $path;
                    $review->photos()->create(['disk' => 'public', 'path' => $path, 'sort_order' => $i]);
                }

                return $review;
            });
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($stored);
            throw $e;
        }
    }

    public function moderate(Review $review, string $status, User $actor): Review
    {
        return DB::transaction(function () use ($review, $status, $actor) {
            $before = ['status' => $review->status];
            $review->forceFill(['status' => $status, 'moderated_by' => $actor->id, 'moderated_at' => now()])->save();
            $this->refreshRating($review->product_id);
            Audit::record("review.{$status}", $review, $before, ['status' => $status], $actor);
            if ($review->product) {
                StorefrontCache::invalidate(['catalog', "product:{$review->product->slug}"]);
            }

            return $review;
        });
    }

    /** Product rating comes only from approved reviews. */
    public function refreshRating(int $productId): void
    {
        $stats = Review::query()->where('product_id', $productId)->where('status', 'approved')
            ->selectRaw('COUNT(*) AS n, AVG(rating) AS avg_rating')->first();
        Product::withTrashed()->whereKey($productId)->update([
            'rating_count' => (int) ($stats->n ?? 0),
            'rating_avg' => round((float) ($stats->avg_rating ?? 0), 2),
        ]);
    }

    private function purchasedItemId(Product $product, User $user): ?int
    {
        return OrderItem::query()->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.user_id', $user->id)->where('order_items.product_id', $product->id)
            ->whereIn('orders.payment_status', [PaymentStatus::Captured->value, PaymentStatus::PartiallyRefunded->value])
            ->orderByDesc('order_items.id')->value('order_items.id');
    }
}

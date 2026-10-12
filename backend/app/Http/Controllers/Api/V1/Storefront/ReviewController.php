<?php

namespace App\Http\Controllers\Api\V1\Storefront;

use App\Domain\Reviews\ReviewService;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Review;
use App\Models\ReviewPhoto;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Product reviews: approved ones are public; signed-in customers may write one per product. */
class ReviewController extends Controller
{
    public function index(Request $request, string $slug): JsonResponse
    {
        $request->validate(['page' => ['nullable', 'integer', 'min:1']]);
        $product = Product::query()->visible()->where('slug', $slug)->firstOrFail();
        $approved = Review::query()->where('product_id', $product->id)->where('status', 'approved');
        $page = (clone $approved)->with(['user:id,name', 'photos'])->latest('id')->paginate(6);
        $histogram = (clone $approved)->selectRaw('rating, COUNT(*) AS n')->groupBy('rating')->pluck('n', 'rating');

        return ApiResponse::success([
            'summary' => [
                // From approved reviews themselves, so the summary always matches the list.
                'average' => $histogram->sum() > 0 ? round($histogram->map(fn ($n, $r) => $n * $r)->sum() / $histogram->sum(), 1) : 0,
                'count' => (int) $histogram->sum(),
                'histogram' => collect([5, 4, 3, 2, 1])->mapWithKeys(fn ($r) => [(string) $r => (int) ($histogram[$r] ?? 0)]),
            ],
            'reviews' => collect($page->items())->map(fn (Review $r) => self::present($r))->values(),
        ], meta: ['pagination' => ['page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'last_page' => $page->lastPage()]]);
    }

    public function store(Request $request, string $slug, ReviewService $reviews): JsonResponse
    {
        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['required', 'string', 'min:10', 'max:3000'],
            'photos' => ['nullable', 'array', 'max:3'],
            'photos.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:6144'],
        ]);
        $product = Product::query()->visible()->where('slug', $slug)->firstOrFail();
        $review = $reviews->submit($product, $request->user(), $data, $request->file('photos', []));

        return ApiResponse::success(['uuid' => $review->uuid, 'status' => $review->status], 'Thank you! Your review will appear once it has been checked.', status: 201);
    }

    /** @return array<string, mixed> */
    public static function present(Review $r): array
    {
        return [
            'uuid' => $r->uuid,
            'author' => $r->displayName(),
            'rating' => $r->rating,
            'title' => $r->title,
            'body' => $r->body,
            'verified' => $r->order_item_id !== null,
            'photos' => $r->photos->map(fn (ReviewPhoto $p) => ['url' => $p->url()])->values(),
            'created_at' => $r->created_at?->toIso8601String(),
        ];
    }
}

<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Reviews\ReviewService;
use App\Http\Controllers\Api\V1\Storefront\ReviewController as StorefrontReviews;
use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Admin → Reviews: approve or reject before anything is shown publicly. */
class ReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate(['status' => ['nullable', Rule::in(Review::STATUSES)], 'page' => ['nullable', 'integer', 'min:1']]);
        $page = Review::query()->with(['user:id,name,email', 'photos', 'product:id,uuid,name,slug,product_type'])
            ->where('status', $filters['status'] ?? 'pending')->latest('id')->paginate(20);

        return ApiResponse::success(
            collect($page->items())->map(fn (Review $r) => [
                ...StorefrontReviews::present($r),
                'status' => $r->status,
                'customer' => ['name' => $r->user?->name, 'email' => $r->user?->email],
                'product' => $r->product ? ['uuid' => $r->product->uuid, 'name' => $r->product->name, 'slug' => $r->product->slug, 'product_type' => $r->product->product_type->value] : null,
            ])->values(),
            meta: ['pagination' => ['page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'last_page' => $page->lastPage()],
                'pending' => Review::query()->where('status', 'pending')->count()],
        );
    }

    public function moderate(Request $request, string $review, ReviewService $reviews): JsonResponse
    {
        $review = Review::query()->where('uuid', $review)->firstOrFail();
        $data = $request->validate(['status' => ['required', Rule::in(['approved', 'rejected'])]]);
        $reviews->moderate($review, $data['status'], $request->user());

        return ApiResponse::success(['uuid' => $review->uuid, 'status' => $review->status], $data['status'] === 'approved' ? 'Review published.' : 'Review rejected.');
    }
}

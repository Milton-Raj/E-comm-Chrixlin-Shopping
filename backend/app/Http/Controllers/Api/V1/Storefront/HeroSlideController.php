<?php

namespace App\Http\Controllers\Api\V1\Storefront;

use App\Http\Controllers\Controller;
use App\Models\HeroSlide;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

/** Active homepage hero slides, in order. An empty list means the storefront shows its built-in slides. */
class HeroSlideController extends Controller
{
    public function index(): JsonResponse
    {
        $slides = HeroSlide::query()->where('is_active', true)->whereNotNull('image_path')
            ->orderBy('sort_order')->orderBy('id')->limit(8)->get();

        return ApiResponse::success($slides->map(fn (HeroSlide $s) => $s->toStorefront())->values());
    }
}

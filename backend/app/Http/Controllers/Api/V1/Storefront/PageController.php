<?php

namespace App\Http\Controllers\Api\V1\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

class PageController extends Controller
{
    public function show(string $slug): JsonResponse
    {
        $page = Page::query()->where('slug', $slug)->where('status', 'published')->firstOrFail();

        return ApiResponse::success([
            'slug' => $page->slug,
            'title' => $page->title,
            'paragraphs' => array_values(array_filter(preg_split('/\R{2,}/', (string) $page->body) ?: [])),
            'seo' => ['title' => $page->seo_title ?? $page->title, 'description' => $page->seo_description],
            'updated_at' => $page->updated_at->toIso8601String(),
        ]);
    }
}

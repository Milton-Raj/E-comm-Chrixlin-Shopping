<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Support\Audit\Audit;
use App\Support\Http\ApiResponse;
use App\Support\Storefront\StorefrontCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** CMS pages (PRD §47). Body is plain text; the storefront renders it escaped. */
class ContentController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponse::success(Page::query()->orderBy('title')->get()->map(fn (Page $p) => $this->present($p)));
    }

    public function show(string $page): JsonResponse
    {
        return ApiResponse::success($this->present(Page::query()->where('uuid', $page)->firstOrFail(), true));
    }

    public function save(Request $request, ?string $page = null): JsonResponse
    {
        $model = $page ? Page::query()->where('uuid', $page)->firstOrFail() : new Page;
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('pages', 'slug')->ignore($model->id)],
            'body' => ['nullable', 'string', 'max:100000'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'seo_description' => ['nullable', 'string', 'max:320'],
        ]);
        $before = $model->exists ? $model->only(['title', 'status']) : null;
        $model->fill([...$data, 'published_at' => $data['status'] === 'published' ? ($model->published_at ?? now()) : null])->save();
        Audit::record($before ? 'page.updated' : 'page.created', $model, $before, $model->only(['title', 'status']), $request->user());
        StorefrontCache::invalidate(['content']);

        return ApiResponse::success($this->present($model, true), 'Page saved.', status: $before ? 200 : 201);
    }

    public function destroy(Request $request, string $page): JsonResponse
    {
        $model = Page::query()->where('uuid', $page)->firstOrFail();
        $model->delete();
        Audit::record('page.deleted', $model, ['title' => $model->title], null, $request->user());
        StorefrontCache::invalidate(['content']);

        return ApiResponse::success(message: 'Page deleted.');
    }

    /** @return array<string, mixed> */
    private function present(Page $p, bool $withBody = false): array
    {
        return [
            'uuid' => $p->uuid, 'slug' => $p->slug, 'title' => $p->title, 'status' => $p->status,
            'seo_description' => $p->seo_description, 'updated_at' => $p->updated_at->toIso8601String(),
        ] + ($withBody ? ['body' => $p->body] : []);
    }
}

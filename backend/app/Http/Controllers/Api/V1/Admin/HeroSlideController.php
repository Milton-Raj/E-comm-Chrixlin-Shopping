<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveHeroSlideRequest;
use App\Models\HeroSlide;
use App\Support\Audit\Audit;
use App\Support\Http\ApiResponse;
use App\Support\Storefront\StorefrontCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Homepage hero slideshow (Admin → Content). Changes refresh the storefront homepage. */
class HeroSlideController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponse::success(HeroSlide::query()->orderBy('sort_order')->orderBy('id')->get()->map(fn (HeroSlide $s) => $this->present($s)));
    }

    /** Create (POST /hero-slides) or update (POST /hero-slides/{slide}); multipart because of the image. */
    public function save(SaveHeroSlideRequest $request, ?string $slide = null): JsonResponse
    {
        $model = $slide ? $this->find($slide) : new HeroSlide(['sort_order' => (int) HeroSlide::query()->max('sort_order') + 1]);
        $data = $request->safe()->except('image');
        $before = $model->exists ? $model->only(['title', 'is_active', 'image_path']) : null;
        $oldImage = $model->image_path;

        $model->fill([...$data, 'focal_point' => $data['focal_point'] ?? $model->focal_point]);
        if ($request->hasFile('image')) {
            $model->image_path = (string) $request->file('image')->store('hero', 'public');
        }
        $model->save();

        if ($oldImage && $oldImage !== $model->image_path) {
            Storage::disk('public')->delete($oldImage);
        }
        Audit::record($before ? 'hero_slide.updated' : 'hero_slide.created', $model, $before, $model->only(['title', 'is_active', 'image_path']), $request->user());
        StorefrontCache::invalidate(['content']);

        return ApiResponse::success($this->present($model), 'Slide saved.', status: $before ? 200 : 201);
    }

    public function reorder(Request $request): JsonResponse
    {
        $data = $request->validate(['order' => ['required', 'array', 'max:'.SaveHeroSlideRequest::MAX_SLIDES], 'order.*' => ['required', 'uuid', 'distinct']]);

        DB::transaction(function () use ($data) {
            foreach ($data['order'] as $position => $uuid) {
                HeroSlide::query()->where('uuid', $uuid)->update(['sort_order' => $position + 1]);
            }
        });
        Audit::record('hero_slide.reordered', null, null, ['order' => $data['order']], $request->user());
        StorefrontCache::invalidate(['content']);

        return $this->index();
    }

    public function destroy(Request $request, string $slide): JsonResponse
    {
        $model = $this->find($slide);
        $model->delete();
        if ($model->image_path) {
            Storage::disk('public')->delete($model->image_path);
        }
        Audit::record('hero_slide.deleted', $model, ['title' => $model->title], null, $request->user());
        StorefrontCache::invalidate(['content']);

        return ApiResponse::success(message: 'Slide deleted.');
    }

    private function find(string $uuid): HeroSlide
    {
        return HeroSlide::query()->where('uuid', $uuid)->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function present(HeroSlide $s): array
    {
        return [
            'uuid' => $s->uuid, 'image' => $s->imageUrl(), 'image_alt' => $s->image_alt, 'focal_point' => $s->focal_point,
            'eyebrow' => $s->eyebrow, 'title' => $s->title, 'body' => $s->body, 'cta_label' => $s->cta_label, 'cta_url' => $s->cta_url,
            'sort_order' => $s->sort_order, 'is_active' => $s->is_active,
        ];
    }
}

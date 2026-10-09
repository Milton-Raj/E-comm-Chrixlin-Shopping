<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Content\SiteContent;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Admin → Content → Site text: storefront wording and the homepage story photo. */
class SiteContentController extends Controller
{
    public function show(SiteContent $content): JsonResponse
    {
        return ApiResponse::success($this->present($content));
    }

    public function update(Request $request, SiteContent $content): JsonResponse
    {
        $rules = ['texts' => ['required', 'array']];
        foreach (SiteContent::fields() as $field) {
            $rules['texts.'.str_replace('.', '\.', $field['key'])] = ['nullable', 'string', 'max:'.$field['max']];
        }
        $request->validate($rules);

        $allowed = array_column(SiteContent::fields(), 'key');
        $texts = array_intersect_key((array) $request->input('texts'), array_flip($allowed));
        $content->saveTexts($request->user(), $texts);

        return ApiResponse::success($this->present($content), 'Site text saved. The store updates within a minute.');
    }

    public function editorialImage(Request $request, SiteContent $content): JsonResponse
    {
        $request->validate(['image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240', 'dimensions:min_width=800,min_height=600']]);
        $content->replaceEditorialImage($request->user(), $request->file('image'));

        return ApiResponse::success($this->present($content), $request->hasFile('image') ? 'Photo updated.' : 'Photo reset to the default.');
    }

    /** @return array<string, mixed> */
    private function present(SiteContent $content): array
    {
        return ['fields' => SiteContent::fields(), 'texts' => (object) $content->texts(), 'editorial_image' => $content->editorialImageUrl()];
    }
}

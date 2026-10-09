<?php

namespace App\Http\Requests\Admin;

use App\Models\HeroSlide;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Hero slide text and image. A new slide needs an image; links stay on this site or use https. */
class SaveHeroSlideRequest extends FormRequest
{
    public const FOCAL_POINTS = ['50% 50%', '50% 20%', '50% 80%', '25% 50%', '75% 50%'];

    public const MAX_SLIDES = 8;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creating = $this->route('slide') === null;

        return [
            'image' => [$creating ? 'required' : 'nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240', 'dimensions:min_width=1200,min_height=700'],
            'image_alt' => ['nullable', 'string', 'max:200'],
            'focal_point' => ['nullable', Rule::in(self::FOCAL_POINTS)],
            'eyebrow' => ['nullable', 'string', 'max:80'],
            'title' => ['required', 'string', 'max:120'],
            'body' => ['nullable', 'string', 'max:300'],
            'cta_label' => ['nullable', 'required_with:cta_url', 'string', 'max:40'],
            'cta_url' => ['nullable', 'required_with:cta_label', 'string', 'max:255', 'regex:#^(/(?!/)[^\s]*|https://[^\s]+)$#'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'cta_url.regex' => 'Use a page on this store (starting with /) or a full https:// link.',
            'image.dimensions' => 'Use a photo at least 1200 × 700 pixels so it stays sharp on large screens.',
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->route('slide') === null && HeroSlide::query()->count() >= self::MAX_SLIDES) {
                $validator->errors()->add('image', 'You can have up to '.self::MAX_SLIDES.' slides. Delete one first.');
            }
        }];
    }
}

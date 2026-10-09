<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * One homepage hero slide. The image lives on the public disk; a slide without an
 * image is never shown on the storefront.
 *
 * @property int $id
 * @property string $uuid
 * @property string|null $image_path
 * @property string|null $image_alt
 * @property string $focal_point
 * @property string|null $eyebrow
 * @property string $title
 * @property string|null $body
 * @property string|null $cta_label
 * @property string|null $cta_url
 * @property int $sort_order
 * @property bool $is_active
 */
class HeroSlide extends Model
{
    use HasPublicUuid;

    protected $fillable = ['image_alt', 'focal_point', 'eyebrow', 'title', 'body', 'cta_label', 'cta_url', 'sort_order', 'is_active'];

    protected $attributes = ['focal_point' => '50% 50%', 'sort_order' => 0, 'is_active' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }

    /** @return array<string, mixed> */
    public function toStorefront(): array
    {
        return [
            'uuid' => $this->uuid, 'image' => $this->imageUrl(), 'image_alt' => $this->image_alt, 'focal_point' => $this->focal_point,
            'eyebrow' => $this->eyebrow, 'title' => $this->title, 'body' => $this->body,
            'cta' => $this->cta_label && $this->cta_url ? ['label' => $this->cta_label, 'url' => $this->cta_url] : null,
        ];
    }
}

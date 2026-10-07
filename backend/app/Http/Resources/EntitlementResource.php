<?php

namespace App\Http\Resources;

use App\Models\DigitalEntitlement;
use App\Models\DigitalFile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin DigitalEntitlement */
class EntitlementResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $product = $this->product;
        $files = $product ? $product->files()->where('is_active', true)->get() : collect();
        $media = $product?->media()->first();

        return [
            'uuid' => $this->uuid,
            'order_number' => $this->order->order_number,
            'product' => $product ? ['name' => $product->name, 'slug' => $product->slug, 'image' => $media ? Storage::disk('public')->url($media->path) : null] : null,
            'status' => $this->status,
            'downloads_used' => $this->downloads_used,
            'download_limit' => $this->download_limit,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'usable' => $this->isUsable(),
            'files' => $files->map(fn (DigitalFile $f) => ['uuid' => $f->uuid, 'name' => $f->original_name, 'size_bytes' => $f->size_bytes, 'mime_type' => $f->mime_type])->values(),
            'purchased_at' => $this->created_at->toIso8601String(),
        ];
    }
}

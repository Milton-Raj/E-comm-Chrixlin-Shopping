<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Catalog\Actions\SaveProduct;
use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminProductResource;
use App\Models\DigitalFile;
use App\Models\Product;
use App\Support\Audit\Audit;
use App\Support\Http\ApiResponse;
use App\Support\Storefront\StorefrontCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(ProductStatus::class)],
            'type' => ['nullable', Rule::enum(ProductType::class)],
            'category' => ['nullable', 'uuid'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $products = Product::query()->with(['brand', 'category', 'media', 'variants.inventory'])
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhereHas('variants', fn ($v) => $v->where('sku', 'like', "%{$term}%"))))
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['type'] ?? null, fn ($q, $t) => $q->where('product_type', $t))
            ->when($filters['category'] ?? null, fn ($q, $c) => $q->whereHas('category', fn ($w) => $w->where('uuid', $c)))
            ->latest('updated_at')->paginate(25);

        return ApiResponse::paginated($products, AdminProductResource::class);
    }

    public function show(string $product): JsonResponse
    {
        return ApiResponse::success((new AdminProductResource($this->find($product)))->detailed());
    }

    public function store(Request $request, SaveProduct $save): JsonResponse
    {
        $product = $save->handle(new Product, $request, $request->user());

        return ApiResponse::success((new AdminProductResource($this->find($product->uuid)))->detailed(), 'Product created.', status: 201);
    }

    public function update(Request $request, string $product, SaveProduct $save): JsonResponse
    {
        $model = $save->handle($this->find($product), $request, $request->user());

        return ApiResponse::success((new AdminProductResource($this->find($model->uuid)))->detailed(), 'Product saved.');
    }

    /** Archive, never hard-delete: order history keeps referencing the product (PRD §67). */
    public function destroy(Request $request, string $product): JsonResponse
    {
        $model = $this->find($product);
        $model->forceFill(['status' => ProductStatus::Archived])->save();
        Audit::record('product.archived', $model, ['status' => 'active'], ['status' => 'archived'], $request->user());
        StorefrontCache::invalidate(['catalog', "product:{$model->slug}"]);

        return ApiResponse::success(message: 'Product archived.');
    }

    public function uploadMedia(Request $request, string $product): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192', 'dimensions:min_width=300,min_height=300'],
            'alt_text' => ['nullable', 'string', 'max:200'],
        ]);
        $model = $this->find($product);
        $path = $request->file('image')->store("products/{$model->uuid}", 'public');
        $model->media()->create(['disk' => 'public', 'path' => (string) $path, 'alt_text' => $request->input('alt_text') ?: $model->name, 'sort_order' => (int) $model->media()->max('sort_order') + 1]);
        Audit::record('product.media_added', $model, null, ['path' => $path], $request->user());
        StorefrontCache::invalidate(['catalog']);

        return ApiResponse::success((new AdminProductResource($this->find($model->uuid)))->detailed(), 'Image uploaded.', status: 201);
    }

    public function deleteMedia(Request $request, string $product, string $media): JsonResponse
    {
        $model = $this->find($product);
        $item = $model->media()->where('uuid', $media)->firstOrFail();
        if (! str_starts_with($item->path, 'demo/')) {
            Storage::disk($item->disk)->delete($item->path);
        }
        $item->delete();
        Audit::record('product.media_removed', $model, ['path' => $item->path], null, $request->user());
        StorefrontCache::invalidate(['catalog']);

        return ApiResponse::success((new AdminProductResource($this->find($model->uuid)))->detailed(), 'Image removed.');
    }

    public function uploadFile(Request $request, string $product): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:204800', 'mimes:pdf,zip,epub,mp3,mp4,docx,xlsx,pptx,png,jpg,jpeg']]);
        $model = $this->find($product);
        abort_unless($model->product_type === ProductType::Digital, 422, 'Only digital products have downloadable files.');

        $upload = $request->file('file');
        $path = $upload->store("digital/{$model->uuid}", 'local'); // private disk, outside the web root
        DigitalFile::create([
            'product_id' => $model->id, 'disk' => 'local', 'path' => (string) $path,
            'original_name' => $upload->getClientOriginalName(), 'mime_type' => (string) $upload->getMimeType(),
            'size_bytes' => (int) $upload->getSize(), 'checksum_sha256' => hash_file('sha256', $upload->getRealPath()),
            'uploaded_by' => $request->user()->getKey(),
        ]);
        Audit::record('product.file_uploaded', $model, null, ['name' => $upload->getClientOriginalName()], $request->user());

        return ApiResponse::success((new AdminProductResource($this->find($model->uuid)))->detailed(), 'File uploaded.', status: 201);
    }

    public function deleteFile(Request $request, string $product, string $file): JsonResponse
    {
        $model = $this->find($product);
        $record = $model->files()->where('uuid', $file)->firstOrFail();
        $record->forceFill(['is_active' => false])->save();
        $record->delete(); // soft delete: entitlements history keeps the reference
        Audit::record('product.file_removed', $model, ['name' => $record->original_name], null, $request->user());

        return ApiResponse::success((new AdminProductResource($this->find($model->uuid)))->detailed(), 'File removed.');
    }

    private function find(string $uuid): Product
    {
        return Product::query()->with(['brand', 'category', 'taxClass', 'media', 'variants.inventory', 'digital', 'files'])->where('uuid', $uuid)->firstOrFail();
    }
}

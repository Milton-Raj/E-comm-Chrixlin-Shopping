<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves catalog images from the public disk at /storage/{path} when the web server
 * cannot serve them directly (on Hostinger the API web root only forwards to index.php).
 * Read-only, images only; private and digital files live on the local disk and never pass here.
 */
final class PublicMediaController extends Controller
{
    private const array TYPES = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'avif' => 'image/avif',
        'gif' => 'image/gif',
    ];

    public function __invoke(string $path): BinaryFileResponse
    {
        $type = self::TYPES[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? null;
        $segments = explode('/', $path);

        abort_if($type === null || in_array('..', $segments, true) || in_array('', $segments, true), 404);
        abort_unless(collect($segments)->every(fn (string $s) => ! str_starts_with($s, '.')), 404);

        $disk = Storage::disk('public');
        abort_unless($disk->fileExists($path), 404);

        return response()->file($disk->path($path), [
            'Content-Type' => $type,
            'Cache-Control' => 'public, max-age=604800',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }
}

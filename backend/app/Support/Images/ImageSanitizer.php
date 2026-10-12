<?php

namespace App\Support\Images;

use App\Exceptions\ApiException;
use Illuminate\Http\UploadedFile;

/**
 * Re-encodes an untrusted customer image to WebP: strips EXIF (incl. GPS location),
 * drops anything hidden in the file, and caps the longest side.
 */
class ImageSanitizer
{
    public function toWebp(UploadedFile $file, int $maxEdge = 1600, int $quality = 82): string
    {
        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if ($source === false) {
            throw new ApiException('That image could not be read. Please upload a JPG, PNG or WebP photo.', 422, 'image_unreadable', ['photos' => ['That image could not be read.']]);
        }

        $source = $this->orient($source, $file);
        [$w, $h] = [imagesx($source), imagesy($source)];
        $scale = min(1, $maxEdge / max($w, $h));
        $target = imagecreatetruecolor(max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale)));
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagecopyresampled($target, $source, 0, 0, 0, 0, imagesx($target), imagesy($target), $w, $h);

        ob_start();
        imagewebp($target, null, $quality);
        $bytes = (string) ob_get_clean();
        imagedestroy($source);
        imagedestroy($target);

        return $bytes;
    }

    /** Phone photos store rotation in EXIF; apply it before EXIF is discarded. */
    private function orient(\GdImage $image, UploadedFile $file): \GdImage
    {
        if (! function_exists('exif_read_data') || ! in_array(strtolower((string) $file->getClientOriginalExtension()), ['jpg', 'jpeg'], true)) {
            return $image;
        }
        $orientation = (int) (@exif_read_data($file->getRealPath())['Orientation'] ?? 1);
        $angle = match ($orientation) {
            3 => 180, 6 => -90, 8 => 90, default => 0
        };

        return $angle !== 0 ? (imagerotate($image, $angle, 0) ?: $image) : $image;
    }
}

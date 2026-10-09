<?php

namespace App\Domain\Content;

use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Settings\Settings;
use App\Support\Storefront\StorefrontCache;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Owner-edited storefront text and the homepage story photo (Admin → Content → Site text).
 * Only keys listed in config/site_content.php are stored; blank means "use the default".
 */
class SiteContent
{
    private const TEXTS = 'content.site_text';

    private const EDITORIAL_IMAGE = 'content.editorial_image';

    public function __construct(private readonly Settings $settings) {}

    /** @return list<array{key: string, group: string, label: string, max: int, multiline?: bool}> */
    public static function fields(): array
    {
        return config('site_content.fields');
    }

    /** @return array<string, string> */
    public function texts(): array
    {
        $saved = $this->settings->get(self::TEXTS, []);
        $allowed = array_column(self::fields(), 'key');

        return array_filter(is_array($saved) ? $saved : [], fn ($v, $k) => in_array($k, $allowed, true) && is_string($v) && $v !== '', ARRAY_FILTER_USE_BOTH);
    }

    public function editorialImageUrl(): ?string
    {
        $path = $this->settings->get(self::EDITORIAL_IMAGE);

        return is_string($path) && $path !== '' ? Storage::disk('public')->url($path) : null;
    }

    /** @param  array<string, string|null>  $texts */
    public function saveTexts(User $actor, array $texts): void
    {
        $before = $this->texts();
        $after = array_filter(array_map(fn ($v) => is_string($v) ? trim($v) : '', $texts), fn ($v) => $v !== '');
        $after = array_replace(array_diff_key($before, $texts), $after);

        $this->settings->set(self::TEXTS, $after, $actor->getKey());
        Audit::record('content.site_text_updated', null, $before, $after, $actor);
        StorefrontCache::invalidate(['content']);
    }

    public function replaceEditorialImage(User $actor, ?UploadedFile $file): void
    {
        $old = $this->settings->get(self::EDITORIAL_IMAGE);
        $path = $file ? (string) $file->store('content', 'public') : null;

        $this->settings->set(self::EDITORIAL_IMAGE, $path, $actor->getKey());
        if (is_string($old) && $old !== '' && $old !== $path) {
            Storage::disk('public')->delete($old);
        }
        Audit::record($file ? 'content.editorial_image_updated' : 'content.editorial_image_reset', null, ['path' => $old], ['path' => $path], $actor);
        StorefrontCache::invalidate(['content']);
    }
}

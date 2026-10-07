<?php

namespace App\Support\Settings;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Admin-editable settings with config/commerce.php as the fallback.
 * Keys are "group.key" (e.g. "store.name"). Cached; invalidated on write.
 */
class Settings
{
    private const CACHE_KEY = 'settings:all';

    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();

        if (array_key_exists($key, $all)) {
            return $all[$key];
        }

        return config('commerce.'.$key, $default);
    }

    public function set(string $key, mixed $value, ?int $updatedBy = null): void
    {
        [$group, $name] = explode('.', $key, 2);

        Setting::updateOrCreate(
            ['group' => $group, 'key' => $name],
            ['value' => $value, 'updated_by' => $updatedBy],
        );

        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => Setting::query()
            ->where('is_encrypted', false)
            ->get(['group', 'key', 'value'])
            ->mapWithKeys(fn (Setting $s) => ["{$s->group}.{$s->key}" => $s->value])
            ->all());
    }
}

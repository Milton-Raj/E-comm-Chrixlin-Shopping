<?php

namespace App\Support\Media;

/**
 * Reads the video id out of the YouTube links people paste: watch?v=, youtu.be/, /shorts/,
 * /embed/ and /live/. Only YouTube hosts are accepted, and only the 11-character id is
 * stored, so nothing pasted is ever echoed back into a page.
 */
final class YouTube
{
    private const HOSTS = ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtu.be', 'www.youtube-nocookie.com', 'youtube-nocookie.com'];

    public static function videoId(string $url): ?string
    {
        $parts = parse_url(trim($url));
        $host = strtolower($parts['host'] ?? '');
        if (! in_array($host, self::HOSTS, true) || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            return null;
        }

        $path = trim($parts['path'] ?? '', '/');
        if ($host === 'youtu.be') {
            $candidate = explode('/', $path)[0];
        } elseif ($path === 'watch') {
            parse_str($parts['query'] ?? '', $query);
            $candidate = is_string($query['v'] ?? null) ? $query['v'] : '';
        } elseif (preg_match('#^(?:shorts|embed|live)/([^/]+)#', $path, $m)) {
            $candidate = $m[1];
        } else {
            return null;
        }

        return preg_match('/^[A-Za-z0-9_-]{11}$/', $candidate) ? $candidate : null;
    }

    public static function thumbnail(string $id): string
    {
        return "https://i.ytimg.com/vi/{$id}/hqdefault.jpg";
    }

    public static function watchUrl(string $id): string
    {
        return "https://www.youtube.com/watch?v={$id}";
    }
}

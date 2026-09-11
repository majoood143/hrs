<?php

namespace App\Support;

class YouTube
{
    /**
     * Extract an 11-character YouTube video ID out of a watch/share/shorts/embed
     * URL, or a pasted <iframe> embed snippet.
     */
    public static function videoId(?string $input): ?string
    {
        if (blank($input)) {
            return null;
        }

        if (preg_match('/(?:youtube(?:-nocookie)?\.com\/(?:watch\?v=|embed\/|shorts\/|live\/)|youtu\.be\/)([A-Za-z0-9_-]{11})/', $input, $matches)) {
            return $matches[1];
        }

        return null;
    }
}

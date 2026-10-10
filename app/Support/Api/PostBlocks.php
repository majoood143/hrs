<?php

namespace App\Support\Api;

use App\Support\Localized;
use App\Support\YouTube;
use Illuminate\Support\Facades\Storage;

/**
 * A blog post's page-builder content (`cms.render-blocks`) as blocks the app draws natively, in the
 * current language. Only the article blocks are translated; any other block type (forms, racing
 * widgets, charts…) becomes one `unsupported` marker, so the app can offer the web page instead.
 */
class PostBlocks
{
    /**
     * @param  array<int, mixed>|null  $content
     * @return list<array<string, mixed>>
     */
    public static function from(?array $content): array
    {
        $blocks = [];

        foreach ($content ?? [] as $block) {
            $type = is_array($block) ? ($block['type'] ?? null) : null;
            $data = is_array($block) ? ($block['data'] ?? []) : [];

            foreach (self::convert($type, is_array($data) ? $data : []) as $converted) {
                // One marker is enough however many unsupported blocks follow each other.
                if ($converted['type'] === 'unsupported' && (end($blocks)['type'] ?? null) === 'unsupported') {
                    continue;
                }

                $blocks[] = $converted;
            }
        }

        return $blocks;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function convert(?string $type, array $data): array
    {
        return match ($type) {
            'heading' => filled($text = Localized::value($data, 'text'))
                ? [['type' => (int) ($data['level'] ?? 2) <= 2 ? 'heading' : 'subheading', 'text' => trim(strip_tags($text))]]
                : [],
            'rich_text' => RichText::blocks(Localized::value($data, 'content')),
            'image' => ! empty($data['image']) && is_string($data['image'])
                ? [[
                    'type' => 'image',
                    'url' => Storage::disk('public')->url($data['image']),
                    'alt' => Localized::value($data, 'alt'),
                    'caption' => Localized::value($data, 'caption'),
                ]]
                : [],
            'video' => ($id = YouTube::videoId(is_string($data['url'] ?? null) ? $data['url'] : null))
                ? [[
                    'type' => 'video',
                    'youtube_id' => $id,
                    'url' => 'https://www.youtube.com/watch?v='.$id,
                    'thumbnail_url' => "https://img.youtube.com/vi/{$id}/hqdefault.jpg",
                    'caption' => Localized::value($data, 'caption'),
                ]]
                : [],
            'divider' => [['type' => 'divider']],
            'cta' => self::cta($data),
            null => [],
            default => [['type' => 'unsupported']],
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function cta(array $data): array
    {
        $url = is_string($data['button_url'] ?? null) ? $data['button_url'] : null;

        return [[
            'type' => 'cta',
            'heading' => Localized::value($data, 'heading'),
            'text' => Localized::value($data, 'subheading'),
            'button_label' => Localized::value($data, 'button_text'),
            'button_url' => Links::absolute($url),
        ]];
    }
}

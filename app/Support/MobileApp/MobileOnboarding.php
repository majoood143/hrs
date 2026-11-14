<?php

namespace App\Support\MobileApp;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Storage;

/**
 * The slides the app shows once at first launch, before the "create your profile" step
 * (admin → Mobile app → Onboarding). Stored in the `mobile.onboarding` site setting; sent in
 * `app-status.onboarding`. `version` changes with the slides, so editing them shows them again once.
 */
final class MobileOnboarding
{
    public const MAX_SLIDES = 3;

    public const DIRECTORY = 'mobile-app/onboarding';

    /** @return array{enabled: bool, slides: list<array{image: ?string, title: array<string, ?string>, text: array<string, ?string>}>} */
    public static function settings(): array
    {
        $stored = self::stored();
        $slides = [];

        foreach (array_slice((array) ($stored['slides'] ?? []), 0, self::MAX_SLIDES) as $slide) {
            $slides[] = [
                'image' => is_string($slide['image'] ?? null) && $slide['image'] !== '' ? $slide['image'] : null,
                'title' => self::texts($slide['title'] ?? []),
                'text' => self::texts($slide['text'] ?? []),
            ];
        }

        return ['enabled' => (bool) ($stored['enabled'] ?? false), 'slides' => $slides];
    }

    /** @param  array<string, mixed>  $state  the admin form's `onboarding` state */
    public static function save(array $state): void
    {
        $before = self::settings();
        $slides = [];

        foreach (array_slice(array_values((array) ($state['slides'] ?? [])), 0, self::MAX_SLIDES) as $slide) {
            // FileUpload's state is a path, or a one-item list of paths while it holds an upload.
            $image = $slide['image'] ?? null;
            $image = is_array($image) ? (array_values($image)[0] ?? null) : $image;
            $slides[] = ['image' => is_string($image) && $image !== '' ? $image : null, 'title' => (array) ($slide['title'] ?? []), 'text' => (array) ($slide['text'] ?? [])];
        }

        SiteSetting::set('mobile.onboarding', json_encode(['enabled' => (bool) ($state['enabled'] ?? false), 'slides' => $slides], JSON_UNESCAPED_UNICODE), 'text', null, 'mobile_app');

        // a replaced or removed picture is not used anywhere else: delete the file
        $kept = array_column(self::settings()['slides'], 'image');
        foreach (array_column($before['slides'], 'image') as $old) {
            if ($old !== null && ! in_array($old, $kept, true) && str_starts_with($old, self::DIRECTORY.'/')) {
                Storage::disk('public')->delete($old);
            }
        }
    }

    /**
     * In the request's language; `enabled` is false when it is off or has no slides.
     *
     * @return array{enabled: bool, version: string|null, slides: list<array{image_url: string|null, title: string, text: string|null}>}
     */
    public static function forClient(): array
    {
        $settings = self::settings();
        $locale = app()->getLocale();
        $fallback = config('languages.default', 'en');

        $slides = array_values(array_filter(array_map(fn (array $slide) => [
            'image_url' => $slide['image'] !== null ? Storage::disk('public')->url($slide['image']) : null,
            'title' => (string) ($slide['title'][$locale] ?? $slide['title'][$fallback] ?? ''),
            'text' => $slide['text'][$locale] ?? $slide['text'][$fallback] ?? null,
        ], $settings['slides']), fn (array $slide) => $slide['title'] !== ''));

        $enabled = $settings['enabled'] && $slides !== [];

        return [
            'enabled' => $enabled,
            // same for both languages, so switching language does not show them again
            'version' => $enabled ? substr(md5(json_encode($settings['slides'])), 0, 10) : null,
            'slides' => $enabled ? $slides : [],
        ];
    }

    /** @return array<string, ?string> */
    private static function texts(mixed $values): array
    {
        $texts = [];

        foreach (MobileUpdates::locales() as $locale) {
            $text = trim((string) (is_array($values) ? ($values[$locale] ?? '') : ''));
            $texts[$locale] = $text !== '' ? $text : null;
        }

        return $texts;
    }

    /** @return array<string, mixed> */
    private static function stored(): array
    {
        $value = SiteSetting::get('mobile.onboarding');
        $decoded = is_string($value) && $value !== '' ? json_decode($value, true) : null;

        return is_array($decoded) ? $decoded : [];
    }
}

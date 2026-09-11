<?php

use App\Models\CmsPage;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private const LEGACY_FIELDS = [
        'heading', 'subheading', 'background_image',
        'primary_button_text', 'primary_button_url',
        'secondary_button_text', 'secondary_button_url',
    ];

    public function up(): void
    {
        CmsPage::query()->whereNotNull('content')->each(function (CmsPage $page): void {
            $content = $page->content ?? [];
            $changed = false;

            foreach ($content as $index => $block) {
                if (($block['type'] ?? null) !== 'hero') {
                    continue;
                }

                $data = $block['data'] ?? [];

                if (! empty($data['slides'])) {
                    continue;
                }

                $slide = [];
                foreach (self::LEGACY_FIELDS as $field) {
                    if (array_key_exists($field, $data)) {
                        $slide[$field] = $data[$field];
                        unset($data[$field]);
                    }
                }

                $data['slides'] = [$slide];
                $content[$index]['data'] = $data;
                $changed = true;
            }

            if ($changed) {
                $page->content = $content;
                $page->saveQuietly();
            }
        });
    }

    public function down(): void
    {
        CmsPage::query()->whereNotNull('content')->each(function (CmsPage $page): void {
            $content = $page->content ?? [];
            $changed = false;

            foreach ($content as $index => $block) {
                if (($block['type'] ?? null) !== 'hero') {
                    continue;
                }

                $data = $block['data'] ?? [];
                $slide = $data['slides'][0] ?? null;

                if ($slide === null) {
                    continue;
                }

                unset($data['slides']);
                $content[$index]['data'] = $slide + $data;
                $changed = true;
            }

            if ($changed) {
                $page->content = $content;
                $page->saveQuietly();
            }
        });
    }
};

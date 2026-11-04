<?php

namespace App\Filament\Support;

use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

/**
 * Every form field gets a placeholder and every upload a size/type hint, from lang/{en,ar}/placeholders.php,
 * so no screen has to remember them. A field's own ->placeholder() / ->hint() still wins (it is set later).
 * Lookup: models.<model>.<field>, then fields.<field>; uploads: dirs.<directory>, models.<model>.<field>, sizes.<field>.
 */
final class FieldPlaceholders
{
    private const TYPE_NAMES = [
        'image/*' => 'JPG, PNG, WebP',
        'image/jpeg' => 'JPG',
        'image/png' => 'PNG',
        'image/webp' => 'WebP',
        'image/gif' => 'GIF',
        'image/svg+xml' => 'SVG',
        'application/pdf' => 'PDF',
        'application/zip' => 'ZIP',
        'application/msword' => 'Word',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'Word',
        'application/vnd.ms-excel' => 'Excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'Excel',
        'video/mp4' => 'MP4',
        'video/webm' => 'WebM',
        'video/quicktime' => 'MOV',
    ];

    /** Livewire's own limit on temporary uploads when a field sets no maxSize. */
    private const DEFAULT_MAX_KB = 12288;

    public static function register(): void
    {
        foreach ([TextInput::class, Textarea::class, RichEditor::class, TagsInput::class] as $class) {
            $class::configureUsing(fn (Field $field) => $field->placeholder(fn (Field $component) => self::text($component)));
        }

        Select::configureUsing(fn (Select $select) => $select->placeholder(fn (Select $component) => self::choose($component)));

        FileUpload::configureUsing(fn (FileUpload $upload) => $upload
            ->hint(fn (FileUpload $component) => self::uploadHint($component))
            ->hintIcon('heroicon-o-information-circle'));
    }

    public static function text(Field $component): ?string
    {
        return $component->isDisabled() ? null : self::lookup($component, ['models', 'fields']);
    }

    public static function choose(Select $component): ?string
    {
        $label = trim(strip_tags((string) $component->getLabel()));

        if ($label === '') {
            return null;
        }

        // "Horse Type" → "horse type", but acronyms stay ("SMS driver")
        $label = preg_replace_callback('/\S+/u', fn (array $w) => mb_strtoupper($w[0]) === $w[0] ? $w[0] : Str::lcfirst($w[0]), $label);

        return __('placeholders.choose', ['label' => $label]);
    }

    public static function uploadHint(FileUpload $component): string
    {
        $directory = str_replace('/', '_', (string) $component->getDirectory());
        $size = $directory !== '' && Lang::has("placeholders.dirs.{$directory}")
            ? __("placeholders.dirs.{$directory}")
            : self::lookup($component, ['models', 'sizes']);

        $types = collect($component->getAcceptedFileTypes() ?? [])
            ->map(fn (string $type) => self::TYPE_NAMES[$type] ?? null)
            ->filter()->unique()->implode(', ');

        $maxMb = round(($component->getMaxSize() ?? self::DEFAULT_MAX_KB) / 1024, 1);

        return collect([$size, $types, __('placeholders.up_to', ['size' => $maxMb])])->filter()->implode(' · ');
    }

    /** @param  array<int, string>  $groups  'models' is model-specific, the rest are keyed by field name */
    private static function lookup(Field $component, array $groups): ?string
    {
        $segments = explode('.', (string) $component->getName());
        $last = array_pop($segments);
        // "title.en" → "title_en", "tamimah.password" → "password"
        $short = in_array($last, ['en', 'ar'], true) && $segments !== [] ? end($segments).'_'.$last : $last;

        $model = $component->getModel();
        $model = $model ? Str::snake(class_basename($model)) : null;

        foreach ($groups as $group) {
            $prefix = $group === 'models' ? ($model ? "models.{$model}" : null) : $group;

            if ($prefix !== null && Lang::has($key = "placeholders.{$prefix}.{$short}")) {
                return __($key);
            }
        }

        return null;
    }
}

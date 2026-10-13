<?php

namespace App\Filament\Support;

use App\Filament\RichEditor\TextDirectionPlugin;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;

/**
 * Form fields directory places (stables, shops…) share: the rich text descriptions and the public
 * photos (also used by the horses-for-sale posts).
 */
final class PlaceFormFields
{
    /** Photos are public files: never SVG (it can carry script), which `image()` alone allows. */
    public const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    public const MAX_PHOTO_KB = 10240;

    public const MAX_GALLERY_PHOTOS = 20;

    /**
     * `en_description` + `ar_description` as rich text (HTML), sanitized when shown through
     * `HasRichDescription::descriptionHtml()`.
     *
     * @return array<int, RichEditor>
     */
    public static function descriptions(string $enLabel, string $arLabel): array
    {
        return [
            self::richText('en_description', $enLabel),
            self::richText('ar_description', $arLabel)->extraInputAttributes(['dir' => 'rtl']),
        ];
    }

    public static function coverPhoto(string $directory, string $label): FileUpload
    {
        return self::photo(FileUpload::make('cover_photo'), $directory, $label);
    }

    public static function gallery(string $directory, string $label, string $name = 'gallery'): FileUpload
    {
        return self::photo(FileUpload::make($name), $directory, $label)
            ->multiple()
            ->reorderable()
            ->maxFiles(self::MAX_GALLERY_PHOTOS);
    }

    private static function richText(string $name, string $label): RichEditor
    {
        return RichEditor::make($name)
            ->label($label)
            ->plugins([TextDirectionPlugin::make()])
            ->toolbarButtons(TextDirectionPlugin::toolbar())
            ->columnSpanFull();
    }

    private static function photo(FileUpload $upload, string $directory, string $label): FileUpload
    {
        return $upload
            ->label($label)
            ->image()
            ->acceptedFileTypes(self::IMAGE_TYPES)
            ->maxSize(self::MAX_PHOTO_KB)
            ->disk('public')
            ->directory($directory);
    }
}

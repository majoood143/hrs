<?php

namespace App\Models\Concerns;

use App\Filament\RichEditor\TextDirectionPlugin;
use Filament\Forms\Components\RichEditor\RichContentRenderer;

/**
 * A directory place whose `en_description`/`ar_description` are rich text from the admin/owner
 * editor (`App\Filament\Support\PlaceFormFields::descriptions()`). The model's `description`
 * accessor picks the current language; never print it raw, use these.
 */
trait HasRichDescription
{
    /**
     * The description in the current language as safe HTML (rich text from the editor, sanitized,
     * keeping alignment, `dir` and links), or null when empty.
     */
    public function descriptionHtml(): ?string
    {
        $html = self::descriptionAsHtml($this->description);

        return $html === null ? null : RichContentRenderer::make($html)
            ->plugins([TextDirectionPlugin::make()])
            ->toHtml();
    }

    /** The description in the current language as plain text (paragraphs on their own lines), for SEO and the app. */
    public function descriptionText(): ?string
    {
        $text = html_entity_decode(
            strip_tags(preg_replace('/<\/(p|h[1-6]|li|blockquote)>|<br\s*\/?>/i', "$0\n", (string) $this->descriptionHtml())),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8',
        );
        $text = trim(preg_replace(['/[ \t]+/u', '/\s*\n\s*/u'], [' ', "\n"], $text));

        return $text === '' ? null : $text;
    }

    /**
     * A stored description as HTML: rich text passes through, plain text (written before the editor,
     * or through the API/seeders) becomes escaped paragraphs, the same way the migration converted it.
     */
    public static function descriptionAsHtml(?string $value): ?string
    {
        if (blank(strip_tags((string) $value))) {
            return null;
        }

        if ($value !== strip_tags($value)) {
            return $value;
        }

        return collect(preg_split('/\R\s*\R/u', trim($value)))
            ->map(fn (string $p) => '<p>'.nl2br(e(trim($p)), false).'</p>')
            ->implode('');
    }
}

<?php

namespace Packstub\FormBuilder\Filament;

use App\Filament\Support\TranslatableInput;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\View;
use Illuminate\Support\Js;

/**
 * The form editor's language switch: "All languages | English | عربي". Choosing one hides the
 * other languages' inputs in the browser only (Filament's client-side visibility), so nothing
 * is dropped from the form state or on save, and the shown input takes the full width. The
 * choice is remembered per browser, and a failed save switches back to all languages so that
 * no error is left out of sight.
 */
class EditorLanguages
{
    /**
     * TranslatableInput::grid(), with each language's input following the switch.
     *
     * @param  callable(string, array<string, mixed>): Field  $factory
     */
    public static function grid(callable $factory, ?int $columns = null): Grid
    {
        return TranslatableInput::grid(
            fn (string $code, array $meta) => $factory($code, $meta)->visibleJs(static::visibleJs($code)),
            $columns,
        )->extraAttributes(['class' => 'fb-lang-grid'], merge: true);
    }

    public static function visibleJs(string $code): string
    {
        return '! $store.fbEditorLang?.only || $store.fbEditorLang.only === '.Js::from($code);
    }

    /** The switch itself; nothing when only one language is configured. */
    public static function switcher(): View
    {
        return View::make('packstub-form-builder::editor.language-switch')
            ->viewData(['locales' => TranslatableInput::locales()])
            ->visible(count(TranslatableInput::locales()) > 1)
            ->columnSpanFull();
    }
}

<?php

namespace App\Filament\RichEditor;

use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Icons\Heroicon;

/**
 * "Left to right" / "Right to left" toolbar buttons for Filament's rich editor:
 * they set `dir` on the selected paragraphs (clicking the active one removes it,
 * so the block follows the page again). Alignment and links are the editor's own.
 *
 *     RichEditor::make('description')->plugins([TextDirectionPlugin::make()])
 *         ->toolbarButtons(TextDirectionPlugin::toolbar())
 *
 * Render the saved HTML with the same plugin so `dir` survives:
 * RichContentRenderer::make($html)->plugins([TextDirectionPlugin::make()])->toHtml().
 */
class TextDirectionPlugin implements RichContentPlugin
{
    public const ASSET = 'rich-content-plugins/text-direction';

    public static function make(): static
    {
        return app(static::class);
    }

    /**
     * A short toolbar for descriptions: text styles, links, alignment, direction, lists.
     *
     * @return array<array<string>>
     */
    public static function toolbar(): array
    {
        return [
            ['bold', 'italic', 'underline', 'strike', 'link'],
            ['h2', 'h3'],
            ['alignStart', 'alignCenter', 'alignEnd', 'alignJustify'],
            ['textDirectionLtr', 'textDirectionRtl'],
            ['blockquote', 'bulletList', 'orderedList'],
            ['clearFormatting', 'undo', 'redo'],
        ];
    }

    public function getTipTapPhpExtensions(): array
    {
        return [app(BlockDirection::class)];
    }

    public function getTipTapJsExtensions(): array
    {
        return [FilamentAsset::getScriptSrc(self::ASSET)];
    }

    public function getEditorTools(): array
    {
        return [
            $this->tool('textDirectionLtr', 'ltr', Heroicon::ArrowLongRight),
            $this->tool('textDirectionRtl', 'rtl', Heroicon::ArrowLongLeft),
        ];
    }

    public function getEditorActions(): array
    {
        return [];
    }

    protected function tool(string $name, string $dir, Heroicon $icon): RichEditorTool
    {
        $isActive = "\$getEditor()?.isActive({ dir: '{$dir}' })";

        return RichEditorTool::make($name)
            ->label(__("rich_editor.direction.{$dir}"))
            ->icon($icon)
            ->jsHandler("{$isActive} ? \$getEditor()?.chain().focus().unsetTextDirection().run() : \$getEditor()?.chain().focus().setTextDirection('{$dir}').run()")
            ->activeJsExpression($isActive)
            ->toggle();
    }
}

<?php

namespace App\Filament\RichEditor;

use Tiptap\Core\Extension;

/**
 * Server half of resources/js/filament/rich-editor/text-direction.js: keeps a
 * block's `dir` (ltr/rtl/auto) when Filament turns stored HTML into the editor's
 * document and back. Without it the attribute is dropped on every save.
 */
class BlockDirection extends Extension
{
    public static $name = 'blockDirection';

    public const TYPES = ['paragraph', 'heading', 'blockquote', 'bulletList', 'orderedList', 'listItem'];

    public function addGlobalAttributes()
    {
        return [
            [
                'types' => self::TYPES,
                'attributes' => [
                    'dir' => [
                        'default' => null,
                        'parseHTML' => function ($DOMNode) {
                            $dir = $DOMNode->getAttribute('dir');

                            return in_array($dir, ['ltr', 'rtl', 'auto'], true) ? $dir : null;
                        },
                        'renderHTML' => fn ($attributes) => in_array($attributes->dir ?? null, ['ltr', 'rtl', 'auto'], true)
                            ? ['dir' => $attributes->dir]
                            : null,
                    ],
                ],
            ],
        ];
    }
}

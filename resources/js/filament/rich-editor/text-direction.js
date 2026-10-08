// A `dir` attribute on the rich editor's blocks, so an Arabic paragraph can sit
// in an English text (and the other way round). Loaded by Filament's rich editor
// through App\Filament\RichEditor\TextDirectionPlugin; the toolbar buttons call
// TipTap's own setTextDirection()/unsetTextDirection() commands, which only need
// the attribute to exist. Built with `npm run build:filament-plugins` into
// resources/js/dist (committed), then published by `php artisan filament:assets`.
import { Extension } from '@tiptap/core'

export const TYPES = ['paragraph', 'heading', 'blockquote', 'bulletList', 'orderedList', 'listItem']

export default Extension.create({
    name: 'blockDirection',

    addGlobalAttributes() {
        return [
            {
                types: TYPES,
                attributes: {
                    dir: {
                        default: null,
                        parseHTML: (element) => {
                            const dir = element.getAttribute('dir')

                            return ['ltr', 'rtl', 'auto'].includes(dir) ? dir : null
                        },
                        renderHTML: (attributes) => (attributes.dir ? { dir: attributes.dir } : {}),
                    },
                },
            },
        ]
    },
})

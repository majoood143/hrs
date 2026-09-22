<?php

namespace Packstub\FormBuilder\Fields\Types;

use Closure;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Packstub\FormBuilder\Fields\Field;
use Packstub\FormBuilder\Fields\FieldType;
use Packstub\FormBuilder\Support\UploadLinks;

/**
 * A file the visitor uploads (a resume, a photo, a document...). Stored on a PRIVATE Laravel
 * filesystem disk — no dependency on the submission row existing yet, so it works whether or
 * not the form keeps submissions — and reached through a signed, permission-checked link.
 */
class FileField extends FieldType
{
    /**
     * Server-executable / script extensions rejected even when a field sets no
     * "accepted_types" allow-list of its own — an admin leaving that field blank
     * must not mean "any file, including a script", only "any ordinary document".
     *
     * @var array<int, string>
     */
    private const DEFAULT_BLOCKED_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'php7', 'phtml', 'phar', 'pht', 'phps',
        'exe', 'com', 'bat', 'cmd', 'scr', 'msi', 'dll', 'vbs', 'vbe', 'ws', 'wsf', 'wsh',
        'sh', 'bash', 'cgi', 'pl', 'py', 'rb', 'jsp', 'jspx', 'asp', 'aspx', 'asa', 'cer',
        'htaccess', 'htpasswd', 'jar', 'ps1', 'reg',
    ];

    public static function id(): string
    {
        return 'file';
    }

    public function icon(): string
    {
        return 'heroicon-o-paper-clip';
    }

    public function editorSchema(): array
    {
        return [
            TagsInput::make('accepted_types')
                ->label(__('packstub-form-builder::form-builder.editor.accepted_types'))
                ->helperText(__('packstub-form-builder::form-builder.editor.accepted_types_hint'))
                ->placeholder('pdf'),
            TextInput::make('max_size')
                ->label(__('packstub-form-builder::form-builder.editor.max_size'))
                ->integer()
                ->minValue(1)
                ->default((int) config('packstub-form-builder.uploads.max_size', 5120)),
        ];
    }

    public function rules(Field $field): array
    {
        $rules = ['file'];

        $extensions = static::extensions($field);

        if ($extensions !== []) {
            $rules[] = 'mimes:'.implode(',', $extensions);
        } else {
            // No allow-list set on the field: fall back to a deny-list so "accept
            // anything" never quietly means "accept a script too".
            $rules[] = static::blockedExtensionRule();
        }

        $rules[] = 'max:'.static::maxSize($field);

        return $rules;
    }

    protected static function blockedExtensionRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! $value instanceof UploadedFile) {
                return;
            }

            $extension = strtolower((string) ($value->getClientOriginalExtension() ?: $value->extension() ?: ''));
            $blocked = (array) config('packstub-form-builder.uploads.blocked_extensions', self::DEFAULT_BLOCKED_EXTENSIONS);

            if ($extension !== '' && in_array($extension, array_map('strtolower', $blocked), true)) {
                $fail(__('packstub-form-builder::form-builder.editor.blocked_extension'));
            }
        };
    }

    /**
     * @param  mixed  $value  An UploadedFile from the plain Blade POST path,
     *                        or an already-stored disk path from the Livewire
     *                        path (Filament's FileUpload persists it itself).
     */
    public function normalize(mixed $value, Field $field): mixed
    {
        if ($value instanceof UploadedFile) {
            $path = Storage::disk(static::disk())->putFile(static::directory(), $value);

            return $path ?: null;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function format(mixed $value, Field $field): string
    {
        if (! is_string($value) || $value === '') {
            return '';
        }

        return UploadLinks::url($value);
    }

    public function view(): string
    {
        return 'packstub-form-builder::fields.file';
    }

    public function formComponent(Field $field): Component
    {
        $upload = FileUpload::make($field->key)
            ->disk(static::disk())
            ->directory(static::directory())
            ->visibility('private')
            ->maxSize(static::maxSize($field));

        $extensions = static::extensions($field);

        if ($extensions !== []) {
            $upload->acceptedFileTypes(array_map(fn (string $extension): string => '.'.$extension, $extensions));
        } else {
            // Filament's own upload handling bypasses FieldType::rules() (the Livewire
            // component validates and stores the file itself), so the deny-list needs
            // to be attached here too.
            $upload->rules([static::blockedExtensionRule()]);
        }

        return $this->configure($upload, $field);
    }

    /**
     * The HTML "accept" attribute for the plain renderer's <input type="file">.
     */
    public function acceptAttribute(Field $field): ?string
    {
        $extensions = static::extensions($field);

        return $extensions === [] ? null : implode(',', array_map(fn (string $extension): string => '.'.$extension, $extensions));
    }

    /**
     * @return array<int, string>
     */
    protected static function extensions(Field $field): array
    {
        return array_values(array_filter(array_map(
            fn (mixed $extension): string => ltrim(strtolower(trim((string) $extension)), '.'),
            (array) $field->option('accepted_types', []),
        )));
    }

    protected static function maxSize(Field $field): int
    {
        $size = $field->option('max_size');

        return filled($size) ? (int) $size : (int) config('packstub-form-builder.uploads.max_size', 5120);
    }

    protected static function disk(): string
    {
        return (string) config('packstub-form-builder.uploads.disk', 'local');
    }

    protected static function directory(): string
    {
        return trim((string) config('packstub-form-builder.uploads.directory', 'form-uploads'), '/');
    }
}

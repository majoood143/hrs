<?php

namespace App\Filament\Support;

use App\Models\Attachement;
use App\Models\Horse;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\Storage;

/**
 * A horse's papers (passport, ID card, DNA report, photos), as the Attachments screen, a
 * transaction and the horse page edit them. Files live on the private disk and open only
 * through a short-lived signed link, never a public URL.
 */
final class AttachmentFields
{
    public const FILE_TYPES = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];

    public const MAX_FILE_KB = 10240;

    private static function label(string $key): string
    {
        return __("admin_attachment.fields.{$key}");
    }

    /**
     * @param  bool  $withHorse  false where the horse is already known (the horse page, a transaction)
     * @return array<int, mixed>
     */
    public static function form(bool $withHorse = true): array
    {
        $name = app()->getLocale() === 'ar' ? 'ar_name' : 'en_name';

        return array_values(array_filter([
            TextInput::make('name')
                ->label(self::label('name'))
                ->maxLength(255),
            Select::make('type')
                ->label(self::label('type'))
                ->options(fn () => Attachement::typeOptions())
                ->required(),
            $withHorse ? Select::make('horse_id')
                ->label(self::label('horse'))
                ->options(fn () => Horse::query()->orderBy($name)->pluck($name, 'id'))
                ->searchable()
                ->required() : null,
            FileUpload::make('file_path')
                ->label(self::label('file'))
                ->helperText(self::label('file_helper'))
                ->disk(Attachement::DISK)
                ->visibility('private')
                ->directory('horse-papers')
                ->acceptedFileTypes(self::FILE_TYPES)
                ->maxSize(self::MAX_FILE_KB)
                ->downloadable()
                ->openable()
                ->required()
                ->columnSpanFull(),
            Textarea::make('description')
                ->label(self::label('description'))
                ->rows(2)
                ->maxLength(255)
                ->columnSpanFull(),
            Toggle::make('is_visible_to_client')
                ->label(self::label('is_visible_to_client')),
            Toggle::make('is_active')
                ->label(self::label('is_active'))
                ->default(true),
            Toggle::make('is_approved')
                ->label(self::label('is_approved')),
        ]));
    }

    /** @return array<int, mixed> */
    public static function columns(bool $withHorse = true): array
    {
        $name = app()->getLocale() === 'ar' ? 'ar_name' : 'en_name';

        return array_values(array_filter([
            TextColumn::make('name')
                ->label(self::label('name'))
                ->placeholder('—')
                ->searchable(),
            TextColumn::make('type')
                ->label(self::label('type'))
                ->badge()
                ->formatStateUsing(fn (string $state): string => Attachement::typeOptions()[$state] ?? $state),
            TextColumn::make('file_path')
                ->label(self::label('file'))
                ->formatStateUsing(fn () => self::label('open'))
                ->icon('heroicon-o-paper-clip')
                ->color('primary')
                // A five-minute signed link to the private file.
                ->url(fn (Attachement $record): ?string => $record->file_path
                    ? Storage::disk(Attachement::DISK)->temporaryUrl($record->file_path, now()->addMinutes(5))
                    : null)
                ->openUrlInNewTab(),
            $withHorse ? TextColumn::make('horse.'.$name)
                ->label(self::label('horse'))
                ->sortable() : null,
            TextColumn::make('user.name')
                ->label(self::label('uploaded_by'))
                ->toggleable(),
            IconColumn::make('is_visible_to_client')
                ->label(self::label('is_visible_to_client'))
                ->boolean(),
            IconColumn::make('is_active')
                ->label(self::label('is_active'))
                ->boolean(),
            IconColumn::make('is_approved')
                ->label(self::label('is_approved'))
                ->boolean(),
            TextColumn::make('created_at')
                ->label(self::label('created_at'))
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ]));
    }
}

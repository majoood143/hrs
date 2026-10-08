<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MediaLibraryResource\Pages\CreateMediaLibrary;
use App\Filament\Resources\MediaLibraryResource\Pages\EditMediaLibrary;
use App\Filament\Resources\MediaLibraryResource\Pages\ListMediaLibraries;
use App\Filament\Resources\MediaLibraryResource\Pages\ListMediaLibraryFiles;
use App\Filament\Resources\MediaLibraryResource\Pages\ManageMediaLibraryFiles;
use App\Models\MediaLibrary;
use Ardavan\FilamentFileExplorer\Resources\Concerns\HasFileExplorerResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MediaLibraryResource extends Resource
{
    use HasFileExplorerResource;

    protected static ?string $model = MediaLibrary::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-folder-open';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('admin_navigation.system');
    }

    public static function getModelLabel(): string
    {
        return __('admin_media_library.navigation.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin_media_library.navigation.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin_media_library.navigation.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('en_name')
                    ->label(__('admin_media_library.fields.en_name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('ar_name')
                    ->label(__('admin_media_library.fields.ar_name'))
                    ->extraInputAttributes(['dir' => 'rtl'])
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('en_name')
                    ->label(__('admin_media_library.fields.en_name'))
                    ->searchable(),
                TextColumn::make('ar_name')
                    ->label(__('admin_media_library.fields.ar_name'))
                    ->searchable(),
                TextColumn::make('files_count')
                    ->label(__('admin_media_library.fields.files'))
                    ->state(fn (MediaLibrary $record): int => $record->fileCount())
                    ->badge()
                    ->color('gray')
                    ->icon('heroicon-o-document'),
                TextColumn::make('created_at')
                    ->label(__('admin_media_library.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                static::openFileExplorerAction(),
                EditAction::make(),
                static::configureDelete(DeleteAction::make()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->modalDescription(__('admin_media_library.delete.bulk_warning')),
                ]),
            ]);
    }

    /** Deleting a library deletes its files too (see MediaLibrary::booted), so say how many. */
    public static function configureDelete(DeleteAction $action): DeleteAction
    {
        return $action->modalDescription(fn (MediaLibrary $record): string => trans_choice(
            'admin_media_library.delete.warning',
            $count = $record->fileCount(),
            ['count' => $count],
        ));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMediaLibraries::route('/'),
            'create' => CreateMediaLibrary::route('/create'),
            'edit' => EditMediaLibrary::route('/{record}/edit'),
            ...static::getFileExplorerPages(ManageMediaLibraryFiles::class, ListMediaLibraryFiles::class),
        ];
    }
}

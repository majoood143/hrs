<?php

namespace App\Filament\Resources\MediaLibraryResource\Pages;

use App\Filament\Resources\MediaLibraryResource;
use App\Support\MediaLinks;
use Ardavan\FilamentFileExplorer\Pages\FileExplorerFilesPage;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Js;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ListMediaLibraryFiles extends FileExplorerFilesPage
{
    protected static string $resource = MediaLibraryResource::class;

    /**
     * The plugin's table plus the public link: a copyable column and Copy/Open actions
     * in front of the plugin's own actions (added through the public Table API only).
     */
    public function table(Table $table): Table
    {
        $table = parent::table($table);

        return $table
            ->pushColumns([
                TextColumn::make('public_link')
                    ->label(__('admin_media_library.share.link'))
                    ->state(fn (Media $record): ?string => MediaLinks::url($record))
                    ->placeholder('—')
                    ->icon('heroicon-o-link')
                    ->limit(45)
                    ->fontFamily('mono')
                    ->size('xs')
                    ->copyable()
                    ->copyMessage(__('admin_media_library.share.copied'))
                    ->extraAttributes(['dir' => 'ltr'])
                    ->toggleable(),
            ])
            ->recordActions([
                Action::make('copyLink')
                    ->label(__('admin_media_library.share.copy'))
                    ->icon('heroicon-o-clipboard-document')
                    ->iconButton()
                    ->tooltip(__('admin_media_library.share.copy'))
                    ->visible(fn (Media $record): bool => MediaLinks::url($record) !== null)
                    ->alpineClickHandler(fn (Media $record): string => 'window.navigator.clipboard.writeText('.Js::from(MediaLinks::url($record)).')'
                        .'.then(() => new FilamentNotification().title('.Js::from(__('admin_media_library.share.copied')).').success().send())'
                        .'.catch(() => new FilamentNotification().title('.Js::from(__('admin_media_library.share.copy_failed')).').warning().send())'),
                Action::make('openLink')
                    ->label(__('admin_media_library.share.open_link'))
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->iconButton()
                    ->tooltip(__('admin_media_library.share.open_link'))
                    ->visible(fn (Media $record): bool => MediaLinks::url($record) !== null)
                    ->url(fn (Media $record): ?string => MediaLinks::url($record))
                    ->openUrlInNewTab(),
                ...$table->getRecordActions(),
            ]);
    }
}

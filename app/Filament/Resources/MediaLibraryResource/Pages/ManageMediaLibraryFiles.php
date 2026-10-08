<?php

namespace App\Filament\Resources\MediaLibraryResource\Pages;

use App\Filament\Resources\MediaLibraryResource;
use Ardavan\FilamentFileExplorer\Pages\FileExplorerPage;

class ManageMediaLibraryFiles extends FileExplorerPage
{
    protected static string $resource = MediaLibraryResource::class;

    // Same as the plugin's page view, but renders App\Livewire\MediaExplorer (adds the share bar).
    protected string $view = 'filament.media-library.explorer-page';
}

<?php

namespace App\Filament\Resources\MediaLibraryResource\Pages;

use App\Filament\Resources\MediaLibraryResource;
use Ardavan\FilamentFileExplorer\Pages\FileExplorerFilesPage;

class ListMediaLibraryFiles extends FileExplorerFilesPage
{
    protected static string $resource = MediaLibraryResource::class;
}

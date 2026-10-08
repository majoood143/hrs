<?php

namespace Tests\Concerns;

use App\Models\MediaLibrary;
use App\Models\SiteSetting;
use Ardavan\FilamentFileExplorer\Models\Folder;
use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Support\Facades\Cache;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Media Library (file explorer) tables, a stub admin user and libraries with files.
 * Public links point at https://muhraequine.om/storage like production.
 */
trait MakesMediaLibraries
{
    protected function prepareMediaLibraries(): void
    {
        (require base_path('vendor/ardavan/filament-file-explorer/database/migrations/2024_01_01_000000_create_file_explorer_folders_table.php'))->up();
        (require database_path('migrations/2026_09_07_152631_create_media_table.php'))->up();
        (require database_path('migrations/2026_09_13_000002_create_media_libraries_table.php'))->up();

        config(['filesystems.disks.public.url' => 'https://muhraequine.om/storage']);

        // uploads go through the app's media hooks, which read site settings: none set
        Cache::forever('site_settings.all', collect());
        SiteSetting::resetMemo();
    }

    /** A plain authorizable user (the real User queries the MySQL-only permission tables). */
    protected function stubUser(int $id): AuthUser
    {
        return (new class extends AuthUser
        {
            protected $guarded = [];
        })->forceFill(['id' => $id, 'name' => 'User '.$id, 'email' => "user{$id}@example.com"]);
    }

    /** @return array{MediaLibrary, Media} */
    protected function libraryWithFile(string $disk = 'public', string $fileName = 'change-ownership.pdf'): array
    {
        $library = MediaLibrary::query()->create(['en_name' => 'Forms', 'ar_name' => 'النماذج']);

        return [$library, $this->addFile($library->fileExplorerRootFolderId(), $disk, $fileName)];
    }

    protected function addFile(int $folderId, string $disk = 'public', string $fileName = 'change-ownership.pdf'): Media
    {
        return Media::query()->create([
            'model_type' => (new Folder)->getMorphClass(),
            'model_id' => $folderId,
            'collection_name' => config('filament-file-explorer.collection'),
            'name' => $fileName,
            'file_name' => $fileName,
            'mime_type' => 'application/pdf',
            'disk' => $disk,
            'size' => 48_213,
            'manipulations' => [],
            'custom_properties' => [],
            'generated_conversions' => [],
            'responsive_images' => [],
        ]);
    }
}

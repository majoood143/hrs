<?php

namespace App\Support;

use App\Models\MediaLibrary;
use Ardavan\FilamentFileExplorer\Contracts\FileExplorerAuthorizer;
use Ardavan\FilamentFileExplorer\Models\Folder;
use Illuminate\Support\Facades\Gate;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Access to the file explorer through the MediaLibrary policy (Shield permissions),
 * replacing the plugin's AllowAllAuthorizer. Used by the explorer and by the plugin's
 * open/download/zip routes, so only someone allowed to view a library can fetch its files
 * through them (stable owners and other users without View:MediaLibrary are refused).
 *
 * A root folder that belongs to no MediaLibrary is refused: the explorer is only used there.
 * Note: files on the public disk stay reachable at their /storage link by design (sharing).
 */
class MediaLibraryAuthorizer implements FileExplorerAuthorizer
{
    /** @var array<int, MediaLibrary|null> */
    private array $libraries = [];

    public function canAccess(string $scopeKey, int $rootFolderId): bool
    {
        $library = $this->library($rootFolderId);

        return $library !== null && Gate::allows('view', $library);
    }

    public function abilities(string $scopeKey, int $rootFolderId): array
    {
        $library = $this->library($rootFolderId);
        $view = $library !== null && Gate::allows('view', $library);
        // Working on the files is editing the library; deleting the library itself stays Delete.
        $edit = $view && Gate::allows('update', $library);

        return [
            'browse' => $view,
            'search' => $view,
            'getInfo' => $view,
            'download' => $view,
            'upload' => $edit,
            'mkdir' => $edit,
            'rename' => $edit,
            'move' => $edit,
            'copy' => $edit,
            'delete' => $edit,
            'deleteFolder' => $edit,
        ];
    }

    public function mediaDeleteState(string $scopeKey, Media $media): array
    {
        $folder = Folder::query()->find($media->model_id);

        return $this->deleteState($folder ? $this->rootOf($folder) : 0);
    }

    public function folderDeleteState(string $scopeKey, Folder $folder): array
    {
        return $this->deleteState($this->rootOf($folder));
    }

    /**
     * @return array{allowed: bool, reason_code: string|null, reason: string|null, remaining_seconds: int|null, window_seconds: int}
     */
    private function deleteState(int $rootFolderId): array
    {
        $allowed = $this->abilities('', $rootFolderId)['delete'];

        return [
            'allowed' => $allowed,
            'reason_code' => $allowed ? null : 'forbidden',
            'reason' => $allowed ? null : __('admin_media_library.access.no_delete'),
            'remaining_seconds' => null,
            'window_seconds' => 0,
        ];
    }

    private function library(int $rootFolderId): ?MediaLibrary
    {
        if ($rootFolderId <= 0) {
            return null;
        }

        if (! array_key_exists($rootFolderId, $this->libraries)) {
            $this->libraries[$rootFolderId] = MediaLibrary::query()->where('folder_id', $rootFolderId)->first();
        }

        return $this->libraries[$rootFolderId];
    }

    private function rootOf(Folder $folder): int
    {
        $current = $folder;
        $guard = 0;

        while ($current->parent_id !== null && $guard++ < 64) {
            $parent = Folder::query()->find($current->parent_id);

            if (! $parent) {
                break;
            }

            $current = $parent;
        }

        return (int) $current->id;
    }
}

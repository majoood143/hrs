<?php

namespace App\Models;

use Ardavan\FilamentFileExplorer\Models\Concerns\HasFileExplorer;
use Ardavan\FilamentFileExplorer\Models\Folder;
use Ardavan\FilamentFileExplorer\Support\FolderTree;
use Ardavan\FilamentFileExplorer\Support\UploadRules;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaLibrary extends Model
{
    use HasFileExplorer;

    protected $fillable = ['en_name', 'ar_name'];

    protected static function booted(): void
    {
        // The root folder carries the English name (not the creator's language) and follows renames.
        static::updated(function (MediaLibrary $library): void {
            if ($library->wasChanged(['en_name', 'ar_name'])) {
                $library->folder?->update(['name' => $library->fileExplorerRootFolderName()]);
            }
        });

        // A deleted library takes its folders and files with it; otherwise they stay on disk
        // (and publicly reachable at their links) with no screen left to find them.
        static::deleting(function (MediaLibrary $library): void {
            if ($root = $library->fileExplorerRootFolderId()) {
                $library->deleteFolderTree($root);
            }
        });
    }

    public function getNameAttribute(): string
    {
        return app()->getLocale() === 'ar' ? $this->ar_name : $this->en_name;
    }

    public function fileExplorerRootFolderName(): string
    {
        return (string) ($this->en_name ?: $this->ar_name ?: 'Media Library');
    }

    /** How many files the library holds (shown before deleting it). */
    public function fileCount(): int
    {
        $root = $this->fileExplorerRootFolderId();

        if (! $root) {
            return 0;
        }

        return Media::query()
            ->where('model_type', (new Folder)->getMorphClass())
            ->whereIn('model_id', app(FolderTree::class)->descendantFolderIdsIncludingRoot($root))
            ->where('collection_name', UploadRules::collection())
            ->count();
    }

    private function deleteFolderTree(int $root): void
    {
        $ids = app(FolderTree::class)->descendantFolderIdsIncludingRoot($root);

        // Every folder of the tree goes (no foreign keys between them, so order does not matter);
        // each file is deleted through its model so Spatie removes it from the disk.
        Folder::query()->whereIn('id', $ids)->get()->each(function (Folder $folder): void {
            $folder->getMedia(UploadRules::collection())->each->delete();
            $folder->delete();
        });
    }
}

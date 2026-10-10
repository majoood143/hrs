<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\VideoFolderResource;
use App\Http\Resources\V1\VideoResource;
use App\Models\Video;
use App\Models\VideoFolder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The website's video library (`Site\VideoLibraryController`): active folders and videos only.
 */
class VideoLibraryController extends Controller
{
    /**
     * List top-level video folders
     *
     * @unauthenticated
     */
    public function folders(): AnonymousResourceCollection
    {
        return VideoFolderResource::collection(VideoFolder::active()->roots()->ordered()->with('media')->get());
    }

    /**
     * Show a video folder
     *
     * The folder, its active sub-folders and its own videos.
     *
     * @unauthenticated
     */
    public function folder(string $slug): array
    {
        $folder = VideoFolder::active()->with(['parent', 'media'])->where('slug', $slug)->firstOrFail();
        $parent = $folder->parent?->is_active ? $folder->parent : null;

        return [
            'data' => [
                'folder' => new VideoFolderResource($folder),
                /** Where "back" goes; null at the top. */
                'parent' => $parent ? new VideoFolderResource($parent) : null,
                'children' => VideoFolderResource::collection($folder->children()->active()->ordered()->with('media')->get()),
                'videos' => VideoResource::collection($folder->videos()->active()->latest()->latest('id')->get()),
            ],
        ];
    }

    /**
     * Show a video
     *
     * @unauthenticated
     */
    public function show(string $slug): VideoResource
    {
        return new VideoResource(Video::active()->with('folder')->where('slug', $slug)->firstOrFail());
    }
}

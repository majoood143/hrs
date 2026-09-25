<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Models\Video;
use App\Models\VideoFolder;
use App\Support\Seo;
use App\Support\ViewCounter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class VideoLibraryController extends Controller
{
    public function index(): View
    {
        $folders = VideoFolder::active()
            ->roots()
            ->ordered()
            ->with(['children' => fn ($query) => $query->active(), 'children.videos', 'videos'])
            ->get();

        return view('site.video-library.index', Seo::forSlug(
            'video-library',
            __('videos.library.title').' — '.SiteSetting::siteName(),
        ) + ['folders' => $folders]);
    }

    public function folder(string $slug): View
    {
        $folder = VideoFolder::active()
            ->where('slug', $slug)
            ->with(['parent'])
            ->firstOrFail();

        $children = $folder->children()->active()->ordered()->with(['children.videos', 'videos'])->get();
        $videos = $folder->videos()->active()->orderBy('order')->orderBy('id')->get();

        return view('site.video-library.folder', [
            'folder' => $folder,
            'children' => $children,
            'videos' => $videos,
            'seoTitle' => $folder->name.' — '.SiteSetting::siteName(),
            'seoDescription' => null,
            'seoImage' => $videos->first()?->thumbnail_url,
        ]);
    }

    public function show(string $slug): View
    {
        $video = Video::active()
            ->with('folder')
            ->where('slug', $slug)
            ->firstOrFail();

        ViewCounter::record($video);

        return view('site.video-library.show', [
            'video' => $video,
            'seoTitle' => $video->title.' — '.SiteSetting::siteName(),
            'seoDescription' => $video->description ? Str::limit($video->description, 160) : null,
            'seoImage' => $video->thumbnail_url,
        ]);
    }
}

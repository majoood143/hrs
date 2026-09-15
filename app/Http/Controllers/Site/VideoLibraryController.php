<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Models\Video;
use App\Models\VideoFolder;
use App\Support\Seo;
use Illuminate\Support\Str;
use Illuminate\View\View;

class VideoLibraryController extends Controller
{
    public function index(): View
    {
        $folders = VideoFolder::active()
            ->orderBy('order')
            ->orderByDesc('id')
            ->with(['videos' => fn ($query) => $query->active()->orderBy('order')->orderBy('id')])
            ->get()
            ->filter(fn (VideoFolder $folder) => $folder->videos->isNotEmpty());

        return view('site.video-library.index', Seo::forSlug(
            'video-library',
            __('videos.library.title') . ' — ' . SiteSetting::siteName(),
        ) + ['folders' => $folders]);
    }

    public function show(string $slug): View
    {
        $video = Video::active()
            ->with('folder')
            ->where('slug', $slug)
            ->firstOrFail();

        return view('site.video-library.show', [
            'video' => $video,
            'seoTitle' => $video->title . ' — ' . SiteSetting::siteName(),
            'seoDescription' => $video->description ? Str::limit($video->description, 160) : null,
            'seoImage' => $video->thumbnail_url,
        ]);
    }
}

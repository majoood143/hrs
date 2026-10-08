<?php

namespace App\Livewire;

use App\Support\MediaLinks;
use App\Support\Silks\SilksLink;
use Ardavan\FilamentFileExplorer\Livewire\FileExplorer;
use Ardavan\FilamentFileExplorer\Models\Folder;
use Ardavan\FilamentFileExplorer\Support\FolderTree;
use Ardavan\FilamentFileExplorer\Support\UploadRules;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Js;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * The plugin's explorer plus a share bar (public link, copy, share, QR code).
 * Its view includes the plugin's view unchanged, so plugin updates keep applying;
 * registered under its own name, so the plugin's form picker stays untouched.
 */
class MediaExplorer extends FileExplorer
{
    /**
     * Link data for every file on screen (current folder or search results), keyed by media id.
     *
     * @return array<int, array{id: int, url: ?string, name: string, size: string, image: bool}>
     */
    public function shareableFiles(): array
    {
        if (! $this->currentFolder) {
            return [];
        }

        $files = collect($this->searchedFiles ?: $this->currentFolder->getMedia(config('filament-file-explorer.collection')));

        return $files
            ->filter(fn ($media): bool => $media instanceof Media)
            ->mapWithKeys(fn (Media $media): array => [(int) $media->id => MediaLinks::payload($media)])
            ->all();
    }

    /**
     * A QR code (SVG) for a file's link, drawn when the share sheet opens. Only for files
     * inside this explorer's root, so ids from elsewhere cannot be probed.
     */
    public function qrSvg(int $mediaId): ?string
    {
        $media = Media::query()->find($mediaId);
        $folder = $media ? Folder::query()->find($media->model_id) : null;

        if (! $folder || ! app(FolderTree::class)->isUnderRoot($folder, $this->rootFolderId)) {
            return null;
        }

        $url = MediaLinks::url($media);
        $rects = $url ? SilksLink::qrRects($url, 200) : '';

        if ($rects === '') {
            return null;
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="-12 -12 224 224" role="img" aria-hidden="true">'
            .'<rect x="-12" y="-12" width="224" height="224" fill="#fff"/><g fill="#000">'.$rects.'</g></svg>';
    }

    /**
     * Same upload as the plugin's, then its notification is put right: the plugin hardcodes a
     * Persian body ("N فایل در «folder»"), which no translation file can override. Ours is
     * translated and, for a single file with a public link, offers "Copy link" and "Open".
     */
    public function updatedFiles(): void
    {
        $lastId = (int) Media::query()->max('id');

        parent::updatedFiles();

        $uploadedTitle = __('filament-file-explorer::file-explorer.uploaded');
        $notifications = session('filament.notifications', []);

        foreach ($notifications as $index => $notification) {
            if (($notification['title'] ?? null) !== $uploadedTitle
                || ! preg_match('/^(\d+) فایل در «(.*)»$/u', (string) ($notification['body'] ?? ''), $match)) {
                continue;
            }

            $notifications[$index] = $this->uploadNotification((int) $match[1], $match[2], $lastId)->toArray();
        }

        session(['filament.notifications' => $notifications]);
    }

    private function uploadNotification(int $count, string $folder, int $lastId): Notification
    {
        $notification = Notification::make()
            ->success()
            ->title(__('filament-file-explorer::file-explorer.uploaded'))
            ->body(trans_choice('admin_media_library.upload.done', $count, ['count' => $count, 'folder' => $folder]));

        if ($count !== 1) {
            return $notification;
        }

        $media = Media::query()
            ->where('id', '>', $lastId)
            ->where('model_type', (new Folder)->getMorphClass())
            ->where('collection_name', UploadRules::collection())
            ->latest('id')
            ->first();
        $url = $media ? MediaLinks::url($media) : null;

        if (! $url) {
            return $notification;
        }

        return $notification
            ->seconds(12)
            ->actions([
                Action::make('copyLink')
                    ->label(__('admin_media_library.share.copy'))
                    ->icon('heroicon-o-clipboard-document')
                    ->button()
                    ->alpineClickHandler('window.navigator.clipboard.writeText('.Js::from($url).')'
                        .'.then(() => new FilamentNotification().title('.Js::from(__('admin_media_library.share.copied')).').success().send())'
                        .'.catch(() => new FilamentNotification().title('.Js::from(__('admin_media_library.share.copy_failed')).').warning().send())'),
                Action::make('openLink')
                    ->label(__('admin_media_library.share.open'))
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->link()
                    ->url($url, shouldOpenInNewTab: true),
            ]);
    }

    public function render()
    {
        return view('filament.media-library.explorer');
    }
}

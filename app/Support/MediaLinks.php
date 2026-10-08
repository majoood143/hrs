<?php

namespace App\Support;

use Ardavan\FilamentFileExplorer\Support\MediaLabel;
use Illuminate\Support\Number;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * The one place that decides a Media Library file's shareable link
 * (today the public disk address, e.g. https://muhraequine.om/storage/9/change-ownership.pdf).
 * Moving files to S3 or to short links later only changes this class.
 */
final class MediaLinks
{
    /**
     * The file's public address, or null when its disk is not public
     * (a private file has no link anyone else could open).
     */
    public static function url(Media $media): ?string
    {
        if (config("filesystems.disks.{$media->disk}.visibility") !== 'public') {
            return null;
        }

        try {
            // always absolute: a link pasted into WhatsApp or an email must work on its own
            return url($media->getUrl());
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * What the explorer's share bar needs to know about a file.
     *
     * @return array{id: int, url: ?string, name: string, size: string, image: bool}
     */
    public static function payload(Media $media): array
    {
        return [
            'id' => (int) $media->id,
            'url' => self::url($media),
            'name' => MediaLabel::display($media),
            'size' => Number::fileSize((int) $media->size, precision: 1),
            'image' => str_starts_with((string) $media->mime_type, 'image/'),
        ];
    }
}

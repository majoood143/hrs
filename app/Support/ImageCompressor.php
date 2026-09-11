<?php

namespace App\Support;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Log;
use Intervention\Image\Laravel\Facades\Image;
use Throwable;

class ImageCompressor
{
    /**
     * Mime types that are safe to decode/re-encode without corrupting the file.
     */
    protected const COMPRESSIBLE_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    /**
     * Mime types whose encoder accepts a lossy "quality" option. PNG is
     * lossless and its encoder has no quality parameter.
     */
    protected const QUALITY_AWARE_MIME_TYPES = [
        'image/jpeg',
        'image/webp',
    ];

    /**
     * Resize (down only) and re-compress an image already saved on disk, in place.
     * Silently no-ops for missing files, non-image files (PDFs, SVGs, etc.),
     * or when disabled via settings. Never throws.
     */
    public static function compress(string $absolutePath): void
    {
        if (! SiteSetting::get('image_compression_enabled', true)) {
            return;
        }

        if (! is_file($absolutePath)) {
            return;
        }

        $mimeType = @mime_content_type($absolutePath);

        if (! in_array($mimeType, self::COMPRESSIBLE_MIME_TYPES, true)) {
            return;
        }

        // Decoding a large photo into an uncompressed bitmap can need far more
        // memory than the default PHP limit, well before any resizing happens.
        $previousMemoryLimit = ini_get('memory_limit');
        ini_set('memory_limit', '512M');

        try {
            $image = Image::decodePath($absolutePath);

            $image->scaleDown(
                width: (int) SiteSetting::get('image_compression_max_width', 2000),
                height: (int) SiteSetting::get('image_compression_max_height', 2000),
            );

            $options = in_array($mimeType, self::QUALITY_AWARE_MIME_TYPES, true)
                ? ['quality' => (int) SiteSetting::get('image_compression_quality', 75)]
                : [];

            $image->save($absolutePath, ...$options);
        } catch (Throwable $exception) {
            Log::warning("Image compression failed for [{$absolutePath}]: {$exception->getMessage()}");
        } finally {
            ini_set('memory_limit', $previousMemoryLimit);
        }
    }
}

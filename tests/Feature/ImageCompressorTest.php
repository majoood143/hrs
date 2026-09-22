<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Support\ImageCompressor;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\PreparesSiteLayout;
use Tests\TestCase;

class ImageCompressorTest extends TestCase
{
    use PreparesSiteLayout;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareSiteLayout();
    }

    private function png(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 40, 40, 40));
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    private function tempFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'imgcompress').'.png';
        file_put_contents($path, $contents);
        $this->beforeApplicationDestroyed(fn () => @unlink($path));

        return $path;
    }

    public function test_an_ordinary_image_within_the_dimension_cap_is_compressed(): void
    {
        $path = $this->tempFile($this->png(400, 300));
        $before = filesize($path);

        ImageCompressor::compress($path);

        $this->assertNotSame($before, filesize($path), 'a fresh, uncompressed PNG should change size once re-encoded');
    }

    public function test_an_image_declaring_dimensions_over_the_configured_cap_is_left_untouched(): void
    {
        $this->seedSettingsFor(['image_compression_max_source_dimension' => 100]);

        $path = $this->tempFile($this->png(400, 300));
        $before = file_get_contents($path);

        ImageCompressor::compress($path);

        // getimagesize() is checked before Image::decodePath() ever runs: the file is left exactly
        // as it was, not partially processed or corrupted.
        $this->assertSame($before, file_get_contents($path));
    }

    public function test_compression_disabled_by_setting_leaves_the_file_untouched(): void
    {
        $this->seedSettingsFor(['image_compression_enabled' => false]);

        $path = $this->tempFile($this->png(400, 300));
        $before = file_get_contents($path);

        ImageCompressor::compress($path);

        $this->assertSame($before, file_get_contents($path));
    }

    /** @param  array<string, mixed>  $settings */
    private function seedSettingsFor(array $settings): void
    {
        Cache::forever('site_settings.all', collect($settings)->map(fn ($value, $key) => (object) [
            'key' => $key,
            'type' => is_bool($value) ? 'boolean' : 'text',
            'value' => is_bool($value) ? ($value ? 'true' : 'false') : (string) $value,
        ]));
        SiteSetting::resetMemo();
    }
}

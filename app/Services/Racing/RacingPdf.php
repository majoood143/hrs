<?php

namespace App\Services\Racing;

use App\Models\SiteSetting;
use App\Support\PdfText;
use App\Services\Pdf\MpdfFactory;
use Mpdf\Output\Destination;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Renders the racing pdf views (resources/views/pdf/racing) to PDF bytes.
 *
 * mPDF does not understand Tailwind (or flex / grid), so the pdf views are their own plain table-and-CSS
 * markup. It is used because it shapes Arabic and lays out right-to-left correctly, which the site needs.
 */
class RacingPdf
{
    /**
     * @param  array<string, mixed>  $data  passed to the view, which also gets $locale, $rtl, $siteName, $logo, $url, $qrUrl, $title and $d()
     */
    public function render(string $view, array $data, string $title, string $url, string $locale): string
    {
        $rtl = $locale === 'ar';
        $siteName = SiteSetting::siteName();

        // mPDF parses the document with regular expressions and a horse with a long career is a big table
        @ini_set('pcre.backtrack_limit', '5000000');

        $mpdf = app(MpdfFactory::class)->make([
            'format' => 'A4-L',
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 12,
            'margin_bottom' => 16,
            'margin_footer' => 6,
        ]);

        $mpdf->SetTitle($title);
        $mpdf->SetAuthor($siteName);
        $mpdf->SetCreator($siteName);
        $mpdf->SetDirectionality($rtl ? 'rtl' : 'ltr');

        // $d(text): the text with its own reading direction kept (see PdfText::dir)
        $common = [
            'locale' => $locale,
            'rtl' => $rtl,
            'siteName' => $siteName,
            'logo' => $this->siteLogo(),
            'url' => $url,
            // the QR code opens the page in the language the PDF was made in
            'qrUrl' => $url . (str_contains($url, '?') ? '&' : '?') . 'lang=' . $locale,
            'title' => $title,
            'd' => fn (?string $text) => PdfText::dir($text, $rtl),
        ];

        $mpdf->SetHTMLFooter(view('pdf.racing._footer', $common)->render());
        $mpdf->WriteHTML(view($view, $data + $common)->render());

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    /**
     * The logo uploaded in General Settings, ready for the page header, or null to fall back to the site name.
     *
     * It is read from the disk and embedded (no HTTP request to ourselves). A raster logo is shrunk first: a
     * 4000 px upload would bloat the file and mPDF's memory for a 14 mm header.
     *
     * @return ?array{src: string, width: float, height: float} src is a data: URI, width / height are millimetres
     */
    public function siteLogo(): ?array
    {
        $path = SiteSetting::get('site_logo');

        if (! is_string($path) || $path === '') {
            return null;
        }

        try {
            $disk = Storage::disk('public');
            $bytes = $disk->exists($path) ? $disk->get($path) : null;

            if ($bytes === null || $bytes === '') {
                return null;
            }

            $image = strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'svg' ? $this->svgLogo($bytes) : $this->rasterLogo($bytes);
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        if ($image === null || $image['ratio'] <= 0) {
            return null;
        }

        // fit a 14 mm tall, 50 mm wide box
        $height = min(14.0, 50.0 / $image['ratio']);

        return ['src' => $image['src'], 'width' => round($height * $image['ratio'], 2), 'height' => round($height, 2)];
    }

    /** @return ?array{src: string, ratio: float} */
    private function rasterLogo(string $bytes): ?array
    {
        $info = @getimagesizefromstring($bytes);

        if (! $info || $info[0] < 1 || $info[1] < 1) {
            return null;
        }

        [$width, $height] = $info;

        // decoding needs ~4 bytes a pixel (and mPDF as much again if we hand it the file): a huge upload would
        // be a fatal out-of-memory that no catch can stop, so it is left out and the site name is used
        if (! $this->fitsInMemory($width * $height * 4 * 2)) {
            return null;
        }

        if (! function_exists('imagecreatefromstring') || ! ($source = @imagecreatefromstring($bytes))) {
            // no GD, or a format it cannot read: hand the file over as it is (mPDF reads png / jpg / gif)
            return in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_GIF], true)
                ? ['src' => 'data:' . $info['mime'] . ';base64,' . base64_encode($bytes), 'ratio' => $width / $height]
                : null;
        }

        $scale = min(1, 600 / $width, 240 / $height);
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        // PNG out, with transparency kept, whatever came in (jpg / gif / webp / png)
        $canvas = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 255, 255, 255, 127));
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        ob_start();
        imagepng($canvas);
        $png = (string) ob_get_clean();

        return ['src' => 'data:image/png;base64,' . base64_encode($png), 'ratio' => $newWidth / $newHeight];
    }

    /** @return ?array{src: string, ratio: float} */
    private function svgLogo(string $svg): ?array
    {
        if (! preg_match('/<svg\b[^>]*>/i', $svg, $tag)) {
            return null;
        }

        // the shape comes from the viewBox, else from width / height
        if (preg_match('/viewBox\s*=\s*["\']\s*[-\d.]+[\s,]+[-\d.]+[\s,]+([\d.]+)[\s,]+([\d.]+)/i', $tag[0], $box)) {
            [$width, $height] = [(float) $box[1], (float) $box[2]];
        } elseif (preg_match('/\bwidth\s*=\s*["\']([\d.]+)/i', $tag[0], $w) && preg_match('/\bheight\s*=\s*["\']([\d.]+)/i', $tag[0], $h)) {
            [$width, $height] = [(float) $w[1], (float) $h[1]];
        } else {
            return null;
        }

        return $width > 0 && $height > 0 ? ['src' => 'data:image/svg+xml;base64,' . base64_encode($svg), 'ratio' => $width / $height] : null;
    }

    /** Is there room for this many bytes on top of what is in use now (with a little slack for the PDF itself)? */
    private function fitsInMemory(int $bytes): bool
    {
        $limit = ini_get('memory_limit');

        if ($limit === '-1' || $limit === false || $limit === '') {
            return $bytes < 512 * 1024 * 1024;
        }

        $number = (int) $limit;
        $limit = match (strtolower(substr($limit, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };

        return memory_get_usage() + $bytes + 24 * 1024 * 1024 < $limit;
    }
}

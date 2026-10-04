<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Services\Pdf\MpdfFactory;
use App\Services\Racing\RacingPdf;
use App\Support\PdfText;
use App\Support\Silks\SilksCatalog;
use App\Support\Silks\SilksDesign;
use App\Support\Silks\SilksLink;
use App\Support\Silks\SilksTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\ComponentAttributeBag;
use Mpdf\Output\Destination;

class SilksController extends Controller
{
    /** Characters per caption line under the downloaded drawing. */
    private const CAPTION_WIDTH = 44;

    /** Largest drawing the PDF accepts, in bytes and pixels (the script sends 1200 px wide). */
    private const MAX_IMAGE_BYTES = 4 * 1024 * 1024;

    private const MAX_IMAGE_SIDE = 3000;

    /**
     * The design in the query string as a standalone SVG, with the site logo above it and its
     * description underneath, plus a QR code that reopens it when ?page= (the designer page) is
     * one of ours: the "Download SVG" link (?download=1) and the source the designer script turns
     * into a PNG. ?bare=1 leaves out all of that (the picture for the PDF).
     */
    public function image(Request $request): Response
    {
        $design = SilksDesign::fromQuery($request->query(), SilksCatalog::load());
        $description = $design->describe();
        $bare = $request->boolean('bare');

        $svg = view('components.silks.figure', [
            'paint' => $design->paint(),
            'uid' => 'silks',
            'label' => __('silks.figure_label', ['description' => $description]),
            'caption' => $bare ? [] : $this->wrap($description),
            'footer' => $bare ? null : __('silks.designed_on', ['site' => SiteSetting::siteName()]),
            'logo' => $bare ? null : app(RacingPdf::class)->siteLogo(),
            'link' => $bare ? null : SilksLink::for($request->query('page'), $design),
            'linkLabel' => __('silks.scan_to_open'),
            'standalone' => true,
            'attributes' => new ComponentAttributeBag,
        ])->render();

        $headers = [
            'Content-Type' => 'image/svg+xml; charset=UTF-8',
            // the file is ours, but it is opened as a document of this origin: it may run nothing,
            // and show only its own embedded logo
            'Content-Security-Policy' => "default-src 'none'; img-src data:; style-src 'unsafe-inline'",
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=300',
        ];

        if ($request->boolean('download')) {
            $headers['Content-Disposition'] = 'attachment; filename="racing-silks.svg"';
        }

        return response('<?xml version="1.0" encoding="UTF-8"?>'."\n".trim($svg), 200, $headers);
    }

    /**
     * The design as an A4 PDF: logo, drawing, description and a table of each part. mPDF cannot
     * clip SVG shapes, so the drawing comes from the visitor's browser as the PNG it already makes
     * from image(?bare=1); everything else (texts, colours, the logo) is built here from the design.
     */
    public function pdf(Request $request, MpdfFactory $factory): Response
    {
        $request->validate([
            'image' => ['required', 'string', 'max:'.(int) ceil(self::MAX_IMAGE_BYTES * 4 / 3 + 64)],
            'page' => ['nullable', 'string', 'max:2000'],
        ]);

        $png = $this->png($request->string('image')->value());
        abort_if($png === null, 422, 'The drawing must be a PNG image.');

        $design = SilksDesign::fromQuery($request->only($this->designKeys()), SilksCatalog::load());
        $rtl = app()->getLocale() === 'ar';
        $siteName = SiteSetting::siteName();

        $html = view('pdf.silks', [
            'design' => $design,
            'description' => $design->describe(),
            'details' => $design->details(),
            'image' => 'data:image/png;base64,'.base64_encode($png['bytes']),
            'imageRatio' => $png['height'] / $png['width'],
            'logo' => app(RacingPdf::class)->siteLogo(),
            'siteName' => $siteName,
            'link' => SilksLink::for($request->input('page'), $design),
            'locale' => app()->getLocale(),
            'rtl' => $rtl,
            'd' => fn (?string $text) => PdfText::dir($text, $rtl),
        ])->render();

        $mpdf = $factory->make([
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 14,
            'margin_bottom' => 14,
            'default_font_size' => 10,
        ]);
        $mpdf->SetTitle(__('silks.pdf_title'));
        $mpdf->SetAuthor($siteName);
        $mpdf->SetCreator($siteName);
        $mpdf->SetDirectionality($rtl ? 'rtl' : 'ltr');
        $mpdf->WriteHTML($html);

        return response($mpdf->Output('', Destination::STRING_RETURN), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="racing-silks.pdf"',
            'Cache-Control' => 'no-store',
        ]);
    }

    /** @return list<string> */
    private function designKeys(): array
    {
        return collect(SilksTemplate::AREAS)->flatMap(fn ($area) => [$area, $area.'1', $area.'2'])->all();
    }

    /** @return ?array{bytes: string, width: int, height: int} only a real PNG of a sane size */
    private function png(string $dataUrl): ?array
    {
        if (! preg_match('#^data:image/png;base64,([A-Za-z0-9+/]+=*)$#', $dataUrl, $match)) {
            return null;
        }

        $bytes = base64_decode($match[1], true);

        if ($bytes === false || strlen($bytes) > self::MAX_IMAGE_BYTES) {
            return null;
        }

        $size = @getimagesizefromstring($bytes);

        if (! $size || $size[2] !== IMAGETYPE_PNG || $size[0] < 100 || $size[0] > self::MAX_IMAGE_SIDE || $size[1] < 100 || $size[1] > self::MAX_IMAGE_SIDE) {
            return null;
        }

        return ['bytes' => $bytes, 'width' => $size[0], 'height' => $size[1]];
    }

    /** @return list<string> */
    private function wrap(string $text): array
    {
        $lines = [];
        $line = '';

        foreach (preg_split('/\s+/u', trim($text)) as $word) {
            if ($line !== '' && mb_strlen($line.' '.$word) > self::CAPTION_WIDTH) {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $line === '' ? $word : $line.' '.$word;
            }
        }

        return $line === '' ? $lines : [...$lines, $line];
    }
}

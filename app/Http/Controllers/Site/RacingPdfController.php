<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Services\Racing\RacingClient;
use App\Services\Racing\RacingPdf;
use App\Services\Racing\RacingUnavailableException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Throwable;

/** "Download PDF" for a profile (horse / owner / jockey / trainer) or a race page (results / entries / card / form guide). */
class RacingPdfController extends Controller
{
    public function __construct(private readonly RacingClient $racing, private readonly RacingPdf $pdf)
    {
    }

    public function profile(string $entity, int $id): Response|RedirectResponse
    {
        abort_unless(isset(RacingClient::ENTITIES[$entity]), 404);

        try {
            $profile = $this->racing->profile($entity, $id, app()->getLocale());
        } catch (RacingUnavailableException $e) {
            report($e);

            // the page itself explains that the source is down
            return redirect()->route('racing.profile', [$entity, $id]);
        }

        abort_if($profile === null, 404);

        $title = $profile['title'] . ' — ' . __('racing.profile_types.' . $entity);

        return $this->download(
            $this->pdf->render('pdf.racing.profile', [
                'entity' => $entity,
                'profile' => $profile,
                'image' => $this->image($profile['image'] ?? null),
            ], $title, route('racing.profile', [$entity, $id]), app()->getLocale()),
            $profile['title'],
            "{$entity}-{$id}",
        );
    }

    /**
     * The race is vetted first, as on the page: the source hangs on unknown ids for the card / form guide pages.
     */
    public function race(string $page, int $race): Response|RedirectResponse
    {
        abort_unless(isset(RacingClient::RACE_PAGES[$page]), 404);

        $locale = app()->getLocale();

        try {
            abort_unless($this->racing->raceInfo($race)['exists'], 404);

            $meeting = $this->racing->meeting($page, $race, $locale);
            $selected = collect($meeting['races'] ?? [])->firstWhere('id', $race);

            abort_if($selected === null, 404);

            $detail = $this->racing->raceDetail($page, $race, $locale);
        } catch (RacingUnavailableException $e) {
            report($e);

            return redirect()->route('racing.meeting', ['page' => $page, 'race' => $race]);
        }

        // nothing to export until the source has published this page for the race
        abort_unless($detail['available'] ?? false, 404);

        $title = __('racing.pages.' . $page . '.title') . ' — ' . $selected['title'];

        return $this->download(
            $this->pdf->render('pdf.racing.race', [
                'page' => $page,
                'meeting' => $meeting,
                'selected' => $selected,
                'detail' => $detail,
            ], $title, route('racing.meeting', ['page' => $page, 'race' => $race]), $locale),
            $title,
            "race-{$race}-{$page}",
        );
    }

    /** Owner silks come through our proxy path on the page; a PDF needs the bytes, and can live without them. */
    private function image(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        try {
            $image = $this->racing->image($path);
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        return $image ? 'data:' . $image['type'] . ';base64,' . base64_encode($image['body']) : null;
    }

    private function download(string $bytes, string $name, string $fallback): Response
    {
        // Arabic names stay in the file name (UTF-8); the ASCII fallback is for clients that cannot read it
        $clean = trim(preg_replace('/[^\p{L}\p{N}\s._()-]+/u', '', $name) ?? '');
        $clean = preg_replace('/\s+/u', ' ', $clean) ?: $fallback;

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_ATTACHMENT,
                $clean . '.pdf',
                (Str::slug($name) ?: $fallback) . '.pdf',
            ),
            'Cache-Control' => 'private, max-age=300',
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}

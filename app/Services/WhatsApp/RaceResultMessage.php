<?php

namespace App\Services\WhatsApp;

use App\Services\Racing\RacingClient;
use App\Services\Racing\RacingUnavailableException;

/**
 * The admin WhatsApp text with one race's results, as on /racing/results/{race}: the race, the first
 * places (horse, margin, jockey, trainer; the winner's owner), the YouTube replay and the results page.
 * Names come from the source in each language, so the Arabic half has the Arabic names.
 */
final class RaceResultMessage
{
    /** A WhatsAppSettings::ALERTS key; the log types are race_result and race_result_video. */
    public const TYPE = 'race_result';

    /** Places listed. */
    public const PLACES = 6;

    private const MEDALS = ['1' => '🥇', '2' => '🥈', '3' => '🥉', '4' => '4️⃣', '5' => '5️⃣', '6' => '6️⃣'];

    public function __construct(private readonly RacingClient $racing) {}

    /**
     * @param  ?string  $videoUrl  the replay when the race day lists one and the meeting does not (yet)
     * @return ?array{message: string, video_url: ?string, name: ?string} null while the race has no results
     *
     * @throws RacingUnavailableException
     */
    public function build(int $raceId, ?string $videoUrl = null): ?array
    {
        $detail = $this->racing->raceDetail('results', $raceId, 'en');

        if (! ($detail['available'] ?? false) || ($detail['table']['rows'] ?? []) === []) {
            return null;
        }

        $race = $this->race($raceId, 'en');
        $videoUrl = $race['video_url'] ?? self::youTube($videoUrl);

        $message = BilingualMessage::make(function (string $locale) use ($raceId, $videoUrl) {
            $race = $this->race($raceId, $locale);
            $detail = $this->racing->raceDetail('results', $raceId, $locale);

            return implode("\n", array_filter([
                '🏁 '.__('whatsapp.race_result.heading', ['race' => self::raceLabel($race)]),
                $race['meeting'] ? '📍 '.$race['meeting'] : null,
                ($race['info'] ?? []) !== [] ? '🕐 '.implode(' · ', $race['info']) : null,
                $race['running_time'] ? '⏱ '.$race['running_time']['label'].': '.$race['running_time']['value'] : null,
                '',
                ...$this->places($detail),
                '',
                $videoUrl ? '🎥 '.__('whatsapp.race_result.replay', ['link' => $videoUrl]) : '🎥 '.__('whatsapp.race_result.no_replay'),
                '🔗 '.__('whatsapp.race_result.page', ['link' => self::pageUrl($raceId)]),
            ], fn ($line) => $line !== null));
        });

        return ['message' => $message, 'video_url' => $videoUrl, 'name' => $race['title'] ?? null];
    }

    /**
     * The short follow-up once the replay is on YouTube, for results sent before it was.
     *
     * @throws RacingUnavailableException
     */
    public function videoFollowUp(int $raceId, string $videoUrl): string
    {
        return BilingualMessage::make(fn (string $locale) => implode("\n", [
            '🎥 '.__('whatsapp.race_result.video_heading', ['race' => self::raceLabel($this->race($raceId, $locale))]),
            $videoUrl,
            '🔗 '.__('whatsapp.race_result.page', ['link' => self::pageUrl($raceId)]),
        ]));
    }

    /**
     * The replay as the results page lists it.
     *
     * @throws RacingUnavailableException
     */
    public function video(int $raceId): ?string
    {
        return $this->race($raceId, 'en')['video_url'];
    }

    public static function pageUrl(int $raceId): string
    {
        return route('racing.meeting', ['page' => 'results', 'race' => $raceId]);
    }

    /** Only YouTube links are ever sent on (the race-day list can hold other URLs). */
    public static function youTube(?string $url): ?string
    {
        $url = $url !== null ? trim($url) : null;

        return $url && preg_match('#^https://(www\.)?(youtube\.com|youtu\.be)/#i', $url) ? $url : null;
    }

    /**
     * The race as the results page's meeting lists it, with the meeting's heading.
     *
     * @return array{number: ?string, title: ?string, meeting: ?string, info: list<string>, running_time: ?array{label: string, value: string}, video_url: ?string}
     */
    private function race(int $raceId, string $locale): array
    {
        $meeting = $this->racing->meeting('results', $raceId, $locale);
        $race = collect($meeting['races'] ?? [])->firstWhere('id', $raceId) ?? [];

        return [
            'number' => $race['number'] ?? null,
            'title' => $race['title'] ?? null,
            'meeting' => $meeting['heading'] ?? null,
            'info' => $race['info'] ?? [],
            'running_time' => $race['running_time'] ?? null,
            'video_url' => $race['video_url'] ?? null,
        ];
    }

    private static function raceLabel(array $race): string
    {
        $number = $race['number'] ? __('racing.race_n', ['n' => $race['number']]) : null;

        return implode(' — ', array_filter([$number, $race['title']])) ?: '—';
    }

    /**
     * The results table's columns differ by language but the linked cells do not: the horse, trainer
     * and jockey are found by their links; the finishing place and margin are the first two cells.
     *
     * @return list<string>
     */
    private function places(array $detail): array
    {
        $owners = collect($detail['owners'] ?? []);
        $lines = [];

        foreach (array_slice($detail['table']['rows'] ?? [], 0, self::PLACES) as $i => $row) {
            $cell = fn (string $type) => collect($row)->first(fn (array $c) => ($c['link']['type'] ?? null) === $type)['text'] ?? null;

            $place = $row[0]['text'] ?? '';
            $margin = $i > 0 ? ($row[1]['text'] ?? '') : '';
            $horse = $cell('horse') ?? '—';

            $lines[] = (self::MEDALS[$place] ?? '▫️').' '.$place.'. *'.$horse.'*'.($margin !== '' ? ' ('.__('whatsapp.race_result.margin', ['value' => $margin]).')' : '');
            $lines[] = '    '.implode(' · ', array_filter([
                ($jockey = $cell('jockey')) ? __('whatsapp.race_result.jockey', ['name' => $jockey]) : null,
                ($trainer = $cell('trainer')) ? __('whatsapp.race_result.trainer', ['name' => $trainer]) : null,
            ]));

            if ($i === 0) {
                $owner = $owners->firstWhere('n', $place) ?? $owners->first();

                if ($owner) {
                    $lines[] = '    '.__('whatsapp.race_result.owner', ['name' => $owner['name']]);
                }
            }
        }

        return array_values(array_filter($lines, fn (string $line) => trim($line) !== ''));
    }
}

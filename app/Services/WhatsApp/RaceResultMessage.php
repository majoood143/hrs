<?php

namespace App\Services\WhatsApp;

use App\Services\Racing\RacingClient;
use App\Services\Racing\RacingUnavailableException;
use App\Support\Locale;
use Closure;

/**
 * The admin WhatsApp text with one race's results, as on /racing/results/{race}: the race, the first
 * places (horse, margin, jockey, trainer; the winner's owner), the YouTube replay and the results page.
 *
 * Arabic only, as the admins asked (the one exception to BilingualMessage): the source's Arabic names,
 * and every line starts with a right-to-left mark, since WhatsApp sets each line's direction from its
 * first letter and lines starting with an emoji, a number or a Latin value (the source's "1600M ·
 * Purebred Arabian", a link) would otherwise be left-aligned.
 */
final class RaceResultMessage
{
    /** A WhatsAppSettings::ALERTS key; the log types are race_result and race_result_video. */
    public const TYPE = 'race_result';

    /** Places listed. */
    public const PLACES = 6;

    public const LOCALE = 'ar';

    /** U+200F RIGHT-TO-LEFT MARK */
    public const RLM = "\u{200F}";

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

        $message = $this->arabic(function () use ($raceId, $videoUrl) {
            $race = $this->race($raceId, self::LOCALE);
            $detail = $this->racing->raceDetail('results', $raceId, self::LOCALE);

            return array_filter([
                '🏁 '.__('whatsapp.race_result.heading', ['race' => self::raceLabel($race)]),
                $race['meeting'] ? '📍 '.$race['meeting'] : null,
                ($race['info'] ?? []) !== [] ? '🕐 '.implode(' · ', $race['info']) : null,
                $race['running_time'] ? '⏱ '.$race['running_time']['label'].': '.$race['running_time']['value'] : null,
                '',
                ...$this->places($detail),
                '',
                $videoUrl ? '🎥 '.__('whatsapp.race_result.replay', ['link' => $videoUrl]) : '🎥 '.__('whatsapp.race_result.no_replay'),
                '🔗 '.__('whatsapp.race_result.page', ['link' => self::pageUrl($raceId)]),
            ], fn ($line) => $line !== null);
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
        return $this->arabic(fn () => [
            '🎥 '.__('whatsapp.race_result.video_heading', ['race' => self::raceLabel($this->race($raceId, self::LOCALE))]),
            $videoUrl,
            '🔗 '.__('whatsapp.race_result.page', ['link' => self::pageUrl($raceId)]),
        ]);
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

    /** @param  Closure(): list<string>  $lines  built in Arabic; each non-empty line gets a leading RLM */
    private function arabic(Closure $lines): string
    {
        return implode("\n", array_map(
            fn (string $line) => trim($line) === '' ? '' : self::RLM.$line,
            Locale::within(self::LOCALE, $lines),
        ));
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
            // the owners list follows the finishing order (its `n` is the place)
            $owner = $owners->firstWhere('n', $place) ?? $owners->get($i);

            foreach ([
                '🏇' => ['jockey', $cell('jockey')],
                '🧑‍🏫' => ['trainer', $cell('trainer')],
                '👤' => ['owner', $owner['name'] ?? null],
            ] as $icon => [$key, $name]) {
                if ($name !== null && $name !== '') {
                    $lines[] = $icon.' '.__('whatsapp.race_result.'.$key, ['name' => $name]);
                }
            }

            $lines[] = '';
        }

        array_pop($lines);

        return $lines;
    }
}

<?php

namespace App\Services\Racing;

use App\Jobs\SendWhatsAppMessage;
use App\Models\RaceResultAlert;
use App\Services\WhatsApp\RaceResultMessage;
use App\Support\WhatsAppSettings;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Race results to the admins by WhatsApp, one message per race (RaceResultMessage), to the numbers in
 * General Settings → WhatsApp.
 *
 * Automatic (racing:result-alerts, every 10 minutes, switch "Race results" in the settings): each race
 * of today's race day is sent once its results are published. The replay usually reaches YouTube a
 * little after the results, so the check waits up to `racing.result_alert_video_wait` minutes for it;
 * if it comes later still, a short follow-up carries its link. Admin → Race results (WhatsApp) shows
 * each day's races and sends or re-sends any of them by hand.
 */
class RaceResultAlerts
{
    public function __construct(
        private readonly RacingClient $racing,
        private readonly RaceResultMessage $messages,
    ) {}

    public static function autoEnabled(): bool
    {
        return WhatsAppSettings::enabled() && WhatsAppSettings::alertEnabled(RaceResultMessage::TYPE);
    }

    public static function today(): string
    {
        return CarbonImmutable::now(config('racing.timezone', 'Asia/Muscat'))->format('Y-m-d');
    }

    /**
     * A race day's races (in the request's language) with what was sent for each.
     *
     * @return list<array{id: int, name: string, time: ?string, distance: ?int, results: bool, video: ?string, alert: ?RaceResultAlert}>
     *
     * @throws RacingUnavailableException
     */
    public function races(string $date, ?string $locale = null): array
    {
        $races = $this->racing->raceDay($date, $locale ?? app()->getLocale());
        $alerts = RaceResultAlert::query()->whereIn('race_id', array_column($races, 'id'))->get()->keyBy('race_id');

        return array_map(fn (array $race) => [
            'id' => $race['id'],
            'name' => $race['name'],
            'time' => $race['time'],
            'distance' => $race['distance'],
            'results' => $race['results'],
            'video' => RaceResultMessage::youTube($race['video']),
            'alert' => $alerts->get($race['id']),
        ], $races);
    }

    /**
     * Send one race's results now.
     *
     * @param  ?list<string>  $recipients  null = every admin number in the settings
     * @return ?int recipients the message went to; null when the race has no results yet
     *
     * @throws RacingUnavailableException
     */
    public function send(int $raceId, string $date, ?array $recipients = null, string $mode = 'manual', ?Authenticatable $by = null, ?string $videoUrl = null): ?int
    {
        $built = $this->messages->build($raceId, $videoUrl);

        if ($built === null) {
            return null;
        }

        $recipients ??= WhatsAppSettings::recipients();

        foreach ($recipients as $phone) {
            SendWhatsAppMessage::dispatch($phone, $built['message'], RaceResultMessage::TYPE);
        }

        $alert = RaceResultAlert::firstOrNew(['race_id' => $raceId]);
        $alert->fill([
            'race_date' => $date,
            'race_name' => $built['name'] ?? $alert->race_name,
            'results_seen_at' => $alert->results_seen_at ?? now(),
            'sent_at' => now(),
            'mode' => $mode,
            'sent_by' => $by?->getAuthIdentifier(),
            'recipients' => count($recipients),
            'video_url' => $built['video_url'],
            // a message with the replay needs no follow-up
            'video_sent_at' => $built['video_url'] ? now() : null,
        ])->save();

        return count($recipients);
    }

    /** Preview of what send() would send; null while the race has no results. */
    public function preview(int $raceId, ?string $videoUrl = null): ?string
    {
        return $this->messages->build($raceId, $videoUrl)['message'] ?? null;
    }

    /**
     * The automatic check: today's races with results, each sent once.
     *
     * @return array{sent: int, waiting: int, videos: int}
     *
     * @throws RacingUnavailableException
     */
    public function checkToday(): array
    {
        $done = ['sent' => 0, 'waiting' => 0, 'videos' => 0];
        $today = self::today();
        $recipients = WhatsAppSettings::recipients();

        if (! self::autoEnabled() || $recipients === [] || ! array_key_exists($today, $this->racing->raceDaysOfSeasonOf($today))) {
            return $done;
        }

        $wait = (int) config('racing.result_alert_video_wait', 20);

        foreach ($this->races($today, 'en') as $race) {
            if (! $race['results']) {
                continue;
            }

            // the race-day list and the results page do not always get the replay link at the same time
            $race['video'] ??= $this->messages->video($race['id']);

            $alert = RaceResultAlert::firstOrCreate(
                ['race_id' => $race['id']],
                ['race_date' => $today, 'race_name' => $race['name'], 'results_seen_at' => now()],
            );

            if ($alert->sent_at === null) {
                $alert->results_seen_at ??= now();

                if ($race['video'] === null && $alert->results_seen_at->gt(now()->subMinutes($wait))) {
                    $alert->isDirty() && $alert->save();
                    $done['waiting']++;

                    continue;
                }

                // null = the source lists results the fragment does not have yet: the next run tries again
                if ($this->send($race['id'], $today, $recipients, 'auto', null, $race['video']) !== null) {
                    $done['sent']++;
                }

                continue;
            }

            if ($alert->video_sent_at === null && $race['video'] !== null) {
                $message = $this->messages->videoFollowUp($race['id'], $race['video']);

                foreach ($recipients as $phone) {
                    SendWhatsAppMessage::dispatch($phone, $message, RaceResultMessage::TYPE.'_video');
                }

                $alert->update(['video_url' => $race['video'], 'video_sent_at' => now()]);
                $done['videos']++;
            }
        }

        return $done;
    }
}

<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\GameAttempt;
use App\Models\GameCampaign;
use App\Models\GameRace;
use App\Models\SiteSetting;
use App\Services\Games\GameAttemptService;
use App\Services\Games\GameException;
use App\Services\Games\GameScore;
use App\Services\Games\LeaderboardService;
use App\Support\ColorPalette;
use App\Support\Games\CommentaryClips;
use App\Support\Games\RaceCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * The horse racing game's calls for a campaign (`/play/{slug}/…`), on the website session: the game
 * (a static build in public/games/horse-racing) reads its campaign, starts a race, sends the finish,
 * reads the board. Players are signed-in customers. Drafts answer only to a signed-in admin (preview).
 */
class GameController extends Controller
{
    public function __construct(
        private readonly GameAttemptService $attempts,
        private readonly LeaderboardService $leaderboard,
    ) {}

    /** "Play": the sign-in comes first (the auth middleware), then the game for this campaign. */
    public function play(GameCampaign $campaign): RedirectResponse
    {
        $this->ensureVisible($campaign);

        return redirect()->to($campaign->gameUrl());
    }

    /** The commentator's clips: `{ clips: { off: { en: url, ar: url }, … } }`, only the moments uploaded. */
    public function sounds(): JsonResponse
    {
        return response()->json(['clips' => (object) CommentaryClips::urls()]);
    }

    public function config(Request $request, GameCampaign $campaign): JsonResponse
    {
        $this->ensureVisible($campaign);
        $customer = $this->customer();

        return response()->json([
            'campaign' => self::campaignPayload($campaign),
            'player' => $customer ? $this->playerPayload($campaign, $customer) : null,
            'brand' => self::brandPayload(),
            'csrf' => csrf_token(),
            'urls' => [
                'start' => route('games.start', $campaign->slug),
                'finish' => route('games.finish', [$campaign->slug, '__ATTEMPT__']),
                'leaderboard' => route('games.leaderboard', $campaign->slug),
                'sign_in' => route('games.play', $campaign->slug),
            ],
        ]);
    }

    public function start(Request $request, GameCampaign $campaign): JsonResponse
    {
        $this->ensureVisible($campaign);
        $data = $request->validate([
            'horse_style' => ['nullable', 'string', Rule::in(RaceCatalog::HORSE_STYLES)],
            'race' => ['nullable', 'integer'],
            'horse' => ['nullable', 'string', 'max:5'],
        ]);
        $race = isset($data['race']) ? $campaign->races()->whereKey($data['race'])->first() : null;

        try {
            $attempt = $this->attempts->start($campaign, $this->customer(), $data['horse_style'] ?? null, $request->ip(), $request->userAgent(), $race, $data['horse'] ?? null);
        } catch (GameException $e) {
            return $this->error($e);
        }

        return response()->json([
            'attempt' => $attempt->uuid,
            'remaining' => $this->attempts->remaining($campaign, $this->customer()),
        ]);
    }

    public function finish(Request $request, GameCampaign $campaign, string $attempt): JsonResponse
    {
        $model = GameAttempt::query()
            ->where('uuid', $attempt)
            ->where('game_campaign_id', $campaign->id)
            ->where('customer_id', $this->customer()->id)
            ->first();

        if (! $model) {
            return $this->error(new GameException('attempt_closed', status: 404));
        }

        $field = $model->race?->fieldSize() ?? RaceCatalog::race($campaign->race_id)['field'];

        $data = $request->validate([
            'position' => ['required', 'integer', 'min:1', 'max:'.$field],
            'field_size' => ['required', 'integer', Rule::in([$field])],
            'race_time_ms' => ['required', 'integer', 'between:1000,900000'],
            'top_speed_kmh' => ['required', 'numeric', 'between:0,300'],
            'webdriver' => ['nullable', 'boolean'],
        ]);

        try {
            return response()->json($this->attempts->finish($model, [
                'position' => (int) $data['position'],
                'field_size' => (int) $data['field_size'],
                'race_time_ms' => (int) $data['race_time_ms'],
                'top_speed_kmh' => (float) $data['top_speed_kmh'],
                'webdriver' => (bool) ($data['webdriver'] ?? false),
            ]));
        } catch (GameException $e) {
            return $this->error($e);
        }
    }

    public function leaderboard(GameCampaign $campaign): JsonResponse
    {
        $this->ensureVisible($campaign);
        abort_unless($campaign->leaderboard_public || Auth::guard('web')->check(), 404);

        $customer = $this->customer();

        return response()->json($this->leaderboard->publicBoard($campaign) + [
            'me' => $customer ? $this->leaderboard->standing($campaign, $customer) : null,
            'phase' => $campaign->phase(),
        ]);
    }

    /** @return array<string, mixed> the campaign as the game and the page show it, in the current language */
    /**
     * General Settings' branding for the game's screens: the primary colour's shade scale (the game's
     * highlight colour) and the heading/body fonts, the same ones the website loads.
     *
     * @return array{shades: array<int, string>, button: string, button_text: string, fonts: array{heading: array{label: string, google: string}, body: array{label: string, google: string}}}
     */
    public static function brandPayload(): array
    {
        $theme = SiteSetting::branding();
        $fonts = config('fonts', []);
        $font = fn (string $key) => ['label' => $fonts[$key]['label'], 'google' => $fonts[$key]['google']];

        return [
            'shades' => ColorPalette::shades($theme['primary']),
            'button' => $theme['button_color'],
            'button_text' => $theme['button_text_color'],
            'fonts' => ['heading' => $font($theme['heading_font']), 'body' => $font($theme['body_font'])],
        ];
    }

    public static function campaignPayload(GameCampaign $campaign): array
    {
        return [
            'slug' => $campaign->slug,
            'name' => $campaign->name,
            'description' => $campaign->localized('description'),
            'prize_text' => $campaign->localized('prize_text'),
            'terms' => $campaign->localized('terms'),
            'sponsor' => $campaign->localized('sponsor'),
            'sponsor_logo' => $campaign->sponsorLogoUrl(),
            'phase' => $campaign->phase(),
            'starts_at' => $campaign->starts_at->toIso8601String(),
            'ends_at' => $campaign->ends_at->toIso8601String(),
            'race_id' => $campaign->race_id,
            'race_name' => $campaign->raceName(),
            'difficulty' => $campaign->difficulty,
            'attempts_per_day' => $campaign->attempts_per_day,
            'attempts_total' => $campaign->attempts_total,
            'leaderboard_public' => $campaign->leaderboard_public,
            'prizes' => $campaign->prizes->map(fn ($prize) => ['rank' => $prize->rank, 'title' => $prize->title])->values()->all(),
            'races' => $campaign->races->map(fn (GameRace $race) => self::racePayload($race))->values()->all(),
            'place_points' => GameScore::PLACE_POINTS,
        ];
    }

    /**
     * A real race and its field as the player chooses from them. The official result stays out:
     * a player sees how the real race went only after riding it.
     *
     * @return array<string, mixed>
     */
    public static function racePayload(GameRace $race): array
    {
        $field = $race->fieldSize();

        return [
            'id' => $race->id,
            'name' => $race->name,
            'meeting' => app()->getLocale() === 'ar' ? ($race->ar_meeting ?: $race->en_meeting) : $race->en_meeting,
            'date' => $race->race_date->format('Y-m-d'),
            'time' => $race->post_time,
            'distance' => $race->distance,
            'surface' => app()->getLocale() === 'ar' ? ($race->ar_surface ?: $race->en_surface) : $race->en_surface,
            'run' => $race->has_result,
            'field_size' => $field,
            'runners' => array_map(fn (array $runner) => [
                'no' => $runner['no'],
                'gate' => $runner['gate'],
                'horse_id' => $runner['horse_id'],
                'name' => GameRace::text($runner['name']),
                'age' => $runner['age'],
                'colour' => GameRace::text($runner['colour']),
                // the English abbreviation (Bay, Ch, Gr…): the game paints the coat from it
                'coat' => $runner['colour']['en'] ?? null,
                'sex' => GameRace::text($runner['sex']),
                'record' => $runner['record'],
                'owner' => GameRace::text($runner['owner']),
                'trainer' => GameRace::text($runner['trainer']),
                'jockey' => GameRace::text($runner['jockey']),
                'weight' => $runner['weight'],
                'rating' => $runner['rating'],
                'silks_url' => GameRace::silksUrl($runner),
                'multiplier' => GameScore::multiplier($race->ratingRank((string) $runner['no']), $field),
            ], $race->runners),
        ];
    }

    /** @return array<string, mixed> */
    private function playerPayload(GameCampaign $campaign, Customer $customer): array
    {
        return [
            'name' => $customer->name,
            'blocked' => $customer->games_blocked_at !== null,
            'remaining' => $this->attempts->remaining($campaign, $customer),
            'standing' => $this->leaderboard->standing($campaign, $customer),
        ];
    }

    private function customer(): ?Customer
    {
        /** @var Customer|null */
        return Auth::guard('customer')->user();
    }

    private function ensureVisible(GameCampaign $campaign): void
    {
        abort_unless($campaign->status === 'published' || Auth::guard('web')->check(), 404);
    }

    private function error(GameException $e): JsonResponse
    {
        return response()->json(['code' => $e->reason, 'message' => $e->getMessage()], $e->status);
    }
}

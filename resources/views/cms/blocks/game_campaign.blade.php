{{--
    A game campaign (admin → Games): what it is, the prizes, the leaderboard and, for a signed-in
    customer, the races they have left and a Play button that opens the game (public/games/horse-racing)
    full screen over the page. Closing the game reloads the page, so the board and the counts are the
    server's. A guest's Play signs them in first (/play/{slug}, then the game).
--}}
@php
    use App\Models\GameAttempt;
    use App\Models\GameCampaign;
    use App\Services\Games\GameAttemptService;
    use App\Services\Games\LeaderboardService;
    use Illuminate\Support\Facades\Auth;

    $campaign = GameCampaign::query()->with('prizes')->find($data['campaign_id'] ?? null);
    $visible = $campaign && ($campaign->status === 'published' || Auth::guard('web')->check());
@endphp

@if($visible)
    @php
        $customer = Auth::guard('customer')->user();
        $phase = $campaign->phase();
        $remaining = $customer ? app(GameAttemptService::class)->remaining($campaign, $customer) : null;
        $standing = $customer ? app(LeaderboardService::class)->standing($campaign, $customer) : null;
        $board = $campaign->leaderboard_public || Auth::guard('web')->check() ? app(LeaderboardService::class)->publicBoard($campaign) : null;
        $blocked = $customer?->games_blocked_at !== null;
        $canPlay = $customer && $phase === GameCampaign::PHASE_LIVE && ! $blocked && ($remaining['can_play'] ?? true);
        $tz = 'Asia/Muscat';
        $phaseBadge = [
            'live' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
            'upcoming' => 'border-sky-200 bg-sky-50 text-sky-800',
            'ended' => 'border-warm-200 bg-warm-100 text-warm-800',
            'draft' => 'border-amber-200 bg-amber-50 text-amber-800',
        ][$phase];
        $phaseIcon = ['live' => 'heroicon-o-play-circle', 'upcoming' => 'heroicon-o-clock', 'ended' => 'heroicon-o-flag', 'draft' => 'heroicon-o-pencil-square'][$phase];
        $uid = 'game-'.$campaign->id;
        $realRaces = $campaign->races()->count();
        $medal = [1 => 'bg-amber-400 text-amber-950', 2 => 'bg-slate-300 text-slate-900', 3 => 'bg-orange-300 text-orange-950'];
    @endphp

    <section id="{{ $uid }}" class="mx-auto max-w-6xl scroll-mt-24 px-6 py-12" data-reveal data-game-campaign>
        <div class="overflow-hidden rounded-[2rem] bg-warm-950 text-warm-50 shadow-xl">
            <div class="relative">
                @if($cover = $campaign->coverUrl())
                    <img src="{{ $cover }}" alt="" class="h-56 w-full object-cover opacity-70 sm:h-72" loading="lazy">
                @else
                    <div class="h-40 w-full bg-gradient-to-br from-warm-700 via-clay-600 to-warm-900 sm:h-56" aria-hidden="true"></div>
                @endif
                <div class="absolute inset-0 bg-gradient-to-t from-warm-950 via-warm-950/50 to-transparent" aria-hidden="true"></div>
                <div class="absolute inset-x-0 bottom-0 p-6 sm:p-8">
                    <span class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-semibold {{ $phaseBadge }}">
                        <x-dynamic-component :component="$phaseIcon" class="h-4 w-4" aria-hidden="true" />{{ __('games.phases.'.$phase) }}
                    </span>
                    <h2 class="mt-3 font-display text-3xl font-semibold sm:text-4xl">{{ $campaign->name }}</h2>
                    <p class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-warm-100/85">
                        @if($realRaces)
                            <span class="inline-flex items-center gap-1.5"><x-heroicon-o-flag class="h-4 w-4" aria-hidden="true" />{{ trans_choice('games.site.real_races', $realRaces, ['count' => $realRaces]) }}</span>
                        @else
                            <span class="inline-flex items-center gap-1.5"><x-heroicon-o-map-pin class="h-4 w-4" aria-hidden="true" />{{ $campaign->raceName() }} · <span dir="ltr">{{ $campaign->raceDistance() }} m</span></span>
                        @endif
                        <span class="inline-flex items-center gap-1.5"><x-heroicon-o-calendar-days class="h-4 w-4" aria-hidden="true" />{{ $campaign->starts_at->timezone($tz)->translatedFormat('j M, H:i') }} – {{ $campaign->ends_at->timezone($tz)->translatedFormat('j M Y, H:i') }}</span>
                    </p>
                </div>
            </div>

            <div class="grid gap-8 p-6 sm:p-8 lg:grid-cols-[minmax(0,7fr)_minmax(0,5fr)]">
                <div class="space-y-6">
                    @if($description = $campaign->localized('description'))
                        <p class="text-warm-100/90">{{ $description }}</p>
                    @endif

                    <div class="rounded-2xl border border-warm-700 bg-warm-900/60 p-5">
                        @if($phase === GameCampaign::PHASE_LIVE && ! $customer)
                            <p class="text-sm text-warm-100/85">{{ __('games.site.sign_in_to_play') }}</p>
                            <a href="{{ route('games.play', $campaign->slug) }}" class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-full bg-amber-400 px-8 py-3 font-semibold text-warm-950 shadow-lg transition hover:-translate-y-0.5 sm:w-auto">
                                <x-heroicon-o-arrow-right-end-on-rectangle class="h-5 w-5 rtl:rotate-180" aria-hidden="true" />{{ __('games.site.sign_in') }}
                            </a>
                        @else
                            @if($customer)
                                <dl class="grid grid-cols-2 gap-3 text-sm">
                                    <div class="rounded-xl bg-warm-950/60 p-3">
                                        <dt class="flex items-center gap-1.5 text-warm-100/70"><x-heroicon-o-ticket class="h-4 w-4" aria-hidden="true" />{{ __('games.site.races_left') }}</dt>
                                        <dd class="mt-1 text-lg font-semibold">{{ $remaining['today'] ?? $remaining['total'] ?? __('games.site.unlimited') }}@if($remaining['today'] !== null) <span class="text-xs font-normal text-warm-100/60">{{ __('games.site.today') }}</span>@endif</dd>
                                    </div>
                                    <div class="rounded-xl bg-warm-950/60 p-3">
                                        <dt class="flex items-center gap-1.5 text-warm-100/70"><x-heroicon-o-trophy class="h-4 w-4" aria-hidden="true" />{{ __('games.site.your_rank') }}</dt>
                                        <dd class="mt-1 text-lg font-semibold">
                                            @if($standing)
                                                #{{ $standing['rank'] }} <span class="text-xs font-normal text-warm-100/60">{{ $realRaces ? __('games.site.points', ['points' => $standing['score']]) : __('games.site.best_line', ['position' => $standing['position'], 'time' => $standing['time']]) }}</span>
                                            @else
                                                —
                                            @endif
                                        </dd>
                                    </div>
                                </dl>
                            @endif

                            @if($blocked)
                                <p class="mt-4 flex gap-2 text-sm text-rose-200"><x-heroicon-o-no-symbol class="h-5 w-5 shrink-0" aria-hidden="true" />{{ __('games.errors.player_blocked') }}</p>
                            @elseif($phase === GameCampaign::PHASE_UPCOMING)
                                <p class="mt-4 flex gap-2 text-sm text-sky-200"><x-heroicon-o-clock class="h-5 w-5 shrink-0" aria-hidden="true" />{{ __('games.site.opens', ['date' => $campaign->starts_at->timezone($tz)->translatedFormat('j M Y, H:i')]) }}</p>
                            @elseif($phase === GameCampaign::PHASE_ENDED)
                                <p class="mt-4 flex gap-2 text-sm text-warm-100/80"><x-heroicon-o-flag class="h-5 w-5 shrink-0" aria-hidden="true" />{{ __('games.errors.campaign_ended') }}</p>
                            @elseif(! $canPlay)
                                <p class="mt-4 flex gap-2 text-sm text-amber-200"><x-heroicon-o-moon class="h-5 w-5 shrink-0" aria-hidden="true" />{{ __('games.errors.no_attempts_left') }}</p>
                            @endif

                            @if($canPlay)
                                <button type="button" data-game-open="{{ $campaign->gameUrl() }}" class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-full bg-amber-400 px-8 py-3 font-semibold text-warm-950 shadow-lg transition hover:-translate-y-0.5 sm:w-auto">
                                    <x-heroicon-o-play class="h-5 w-5 rtl:rotate-180" aria-hidden="true" />{{ __('games.site.play') }}
                                </button>
                            @endif
                        @endif
                        <p class="mt-4 text-xs text-warm-100/60">{{ $realRaces ? __('games.site.how_it_ranks_points') : __('games.site.how_it_ranks') }}</p>
                    </div>

                    @if($campaign->prizes->isNotEmpty() || $campaign->localized('prize_text'))
                        <div>
                            <h3 class="flex items-center gap-2 font-display text-xl font-semibold"><x-heroicon-o-gift class="h-5 w-5 text-amber-300" aria-hidden="true" />{{ __('games.site.prizes') }}</h3>
                            @if($prizeText = $campaign->localized('prize_text'))
                                <p class="mt-2 text-sm text-warm-100/80">{{ $prizeText }}</p>
                            @endif
                            <ul class="mt-3 grid gap-2 sm:grid-cols-2">
                                @foreach($campaign->prizes as $prize)
                                    <li class="flex items-center gap-3 rounded-xl border border-warm-700 bg-warm-900/60 px-3 py-2.5">
                                        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full text-sm font-bold {{ $medal[$prize->rank] ?? 'bg-warm-700 text-warm-50' }}">{{ $prize->rank }}</span>
                                        <span class="text-sm font-medium">{{ $prize->title }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if($sponsor = $campaign->localized('sponsor'))
                        <p class="flex items-center gap-3 text-sm text-warm-100/75">
                            @if($logo = $campaign->sponsorLogoUrl())<img src="{{ $logo }}" alt="" class="h-10 w-auto rounded bg-white p-1" loading="lazy">@endif
                            {{ __('games.site.sponsored_by', ['sponsor' => $sponsor]) }}
                        </p>
                    @endif
                </div>

                @if($board)
                    <div>
                        <h3 class="flex items-center gap-2 font-display text-xl font-semibold"><x-heroicon-o-chart-bar class="h-5 w-5 text-amber-300" aria-hidden="true" />{{ __('games.site.leaderboard') }}</h3>
                        <p class="mt-1 text-xs text-warm-100/60">
                            {{ trans_choice('games.site.players', $board['players'], ['count' => $board['players']]) }}
                            @if($board['frozen']) · {{ __('games.site.frozen') }}@endif
                        </p>
                        @if($board['rows'] === [])
                            <p class="mt-4 rounded-2xl border border-dashed border-warm-700 px-4 py-8 text-center text-sm text-warm-100/70">{{ __('games.site.no_players') }}</p>
                        @else
                            <ol class="mt-3 divide-y divide-warm-800 overflow-hidden rounded-2xl border border-warm-700 bg-warm-900/60">
                                @foreach($board['rows'] as $row)
                                    <li class="flex items-center gap-3 px-3 py-2.5 text-sm {{ $standing && $standing['rank'] === $row['rank'] ? 'bg-amber-400/15' : '' }}">
                                        <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full text-xs font-bold {{ $medal[$row['rank']] ?? 'bg-warm-800 text-warm-50' }}">{{ $row['rank'] }}</span>
                                        <span class="min-w-0 flex-1 truncate">{{ $row['player'] }}</span>
                                        @if($realRaces)
                                            <span class="font-semibold text-amber-200">{{ __('games.site.points', ['points' => $row['score']]) }}</span>
                                        @else
                                            <span class="text-xs text-warm-100/70">{{ __('games.site.place', ['position' => $row['position']]) }}</span>
                                            <span class="font-mono text-xs" dir="ltr">{{ $row['time'] }}</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ol>
                        @endif
                    </div>
                @endif
            </div>

            @if($terms = $campaign->localized('terms'))
                <details class="border-t border-warm-800 px-6 py-4 sm:px-8">
                    <summary class="flex cursor-pointer items-center gap-2 text-sm font-semibold"><x-heroicon-o-document-text class="h-5 w-5" aria-hidden="true" />{{ __('games.site.terms') }}</summary>
                    <p class="mt-3 whitespace-pre-line text-sm text-warm-100/80">{{ $terms }}</p>
                </details>
            @endif
        </div>

        @if($canPlay)
            <div data-game-overlay hidden class="fixed inset-0 z-[100] bg-black">
                <iframe data-game-frame title="{{ $campaign->name }}" class="h-full w-full border-0" allow="autoplay; fullscreen; accelerometer; gyroscope"></iframe>
                <button type="button" data-game-close class="absolute end-3 top-[max(0.75rem,env(safe-area-inset-top))] inline-flex items-center gap-1.5 rounded-full bg-black/70 px-4 py-2 text-sm font-semibold text-white backdrop-blur">
                    <x-heroicon-o-x-mark class="h-5 w-5" aria-hidden="true" />{{ __('games.site.close') }}
                </button>
            </div>
            <script>
                (() => {
                    const root = document.getElementById(@json($uid));
                    const overlay = root.querySelector('[data-game-overlay]');
                    const frame = root.querySelector('[data-game-frame]');
                    root.querySelector('[data-game-open]').addEventListener('click', (event) => {
                        frame.src = event.currentTarget.dataset.gameOpen;
                        overlay.hidden = false;
                        document.documentElement.classList.add('overflow-hidden');
                    });
                    // the board, the races left and the rank are the server's: read them again
                    root.querySelector('[data-game-close]').addEventListener('click', () => {
                        frame.src = 'about:blank';
                        location.hash = @json($uid);
                        location.reload();
                    });
                })();
            </script>
        @endif
    </section>
@endif

{{-- $race: one entry of RacingMeetingParser::parseMeeting()['races'] --}}
<section class="card-warm mt-6 p-6 sm:p-8">
    <div class="flex flex-col gap-6 md:flex-row md:items-start md:justify-between">
        <div class="min-w-0">
            <p class="section-eyebrow">{{ __('racing.race_n', ['n' => $race['number'] ?? '']) }}</p>
            <h2 class="mt-1 font-display text-2xl font-semibold text-warm-900">{{ $race['title'] }}</h2>

            @if($race['info'])
                <p class="mt-3 flex flex-wrap gap-2 text-sm">
                    @foreach($race['info'] as $item)
                        <span class="badge-accent">{{ $item }}</span>
                    @endforeach
                </p>
            @endif

            @if($race['facts'])
                <dl class="mt-4 flex flex-wrap gap-x-6 gap-y-1 text-sm">
                    @foreach($race['facts'] as $fact)
                        <div class="flex gap-1.5">
                            <dt class="text-warm-900/60">{{ $fact['label'] }}</dt>
                            <dd class="text-warm-900">{{ $fact['value'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            @endif

            @if($race['conditions'])
                <p class="mt-3 text-sm text-warm-900/80">
                    <span class="text-warm-900/60">{{ $race['conditions']['label'] }}</span>
                    <span class="whitespace-pre-line">{{ $race['conditions']['value'] }}</span>
                </p>
            @endif
        </div>

        @if($race['running_time'] || $race['video_url'])
            <div class="shrink-0 space-y-3 text-center md:text-end">
                @if($race['running_time'])
                    <div class="rounded-2xl bg-warm-50 px-6 py-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-warm-700">{{ $race['running_time']['label'] }}</p>
                        <p class="font-display text-2xl font-semibold text-warm-900" dir="ltr">{{ $race['running_time']['value'] }}</p>
                    </div>
                @endif

                @if($race['video_url'])
                    <a href="{{ $race['video_url'] }}" target="_blank" rel="noopener noreferrer" class="btn-warm-outline w-full !px-5 !py-2 text-sm md:w-auto">
                        {{ __('racing.watch_replay') }}
                    </a>
                @endif
            </div>
        @endif
    </div>

    @if($race['prizes'])
        <ul class="mt-6 flex flex-wrap gap-x-6 gap-y-2 border-t border-warm-100 pt-4 text-sm" aria-label="{{ __('racing.prize_money') }}">
            @foreach($race['prizes'] as $prize)
                <li><span class="text-warm-900/60">{{ $prize['place'] }}</span> <span class="font-semibold text-warm-900" dir="ltr">{{ $prize['amount'] }}</span></li>
            @endforeach
        </ul>
    @endif
</section>

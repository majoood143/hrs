{{-- A race prediction field (App\Services\Forms\RaceWinnerField) in the form builder's Blade renderer:
     the race card's header, then the runners as cards to tap. Plain radio buttons inside a <details>,
     so it works without JavaScript; the script below only folds a race once its winner is picked. --}}
@use('App\Services\Forms\RaceWinnerField')
@php
    $race = RaceWinnerField::raceDetails($field);
    $choices = $field->choices();
    $picked = array_key_exists((string) $value, $choices) ? (string) $value : null;
    $pickedRunner = $picked === null ? null : RaceWinnerField::runner($field, $picked);
    $pickedText = fn (string $no, string $name): string => __('race_predictions.your_pick', ['name' => trim($no.' '.$name)]);
@endphp

@once('race-winner-styles')
    <style @if (isset($nonce) && $nonce()) nonce="{{ $nonce() }}" @endif>
        .rw-race{display:flex;flex-direction:column;gap:.375rem;padding:.75rem .875rem;border:1px solid var(--_border);border-radius:calc(var(--_radius) * .6);background:color-mix(in srgb,var(--_primary) 4%,var(--_surface));font-size:.875rem;line-height:1.45}
        .rw-race p{margin:0}
        .rw-race__main{display:flex;flex-wrap:wrap;gap:.25rem .75rem;font-weight:600}
        .rw-race__time{color:var(--_primary)}
        .rw-race__facts{display:flex;flex-wrap:wrap;gap:.25rem .875rem}
        .rw-muted{color:var(--_muted)}
        .rw-prizes summary{cursor:pointer;color:var(--_muted);width:max-content}
        .rw-prizes ol{list-style:none;margin:.375rem 0 0;padding:0;display:flex;flex-wrap:wrap;gap:.25rem 1rem}
        .rw-prizes li{display:inline-flex;gap:.375rem;align-items:center}
        .rw-pick{border:1px solid var(--_border);border-radius:calc(var(--_radius) * .6);background:var(--_surface)}
        .rw-pick__summary{display:flex;align-items:center;justify-content:space-between;gap:.75rem;padding:.6875rem .875rem;cursor:pointer;list-style:none;font-weight:600}
        .rw-pick__summary::-webkit-details-marker{display:none}
        .rw-pick__summary:focus-visible{outline:2px solid var(--_primary);outline-offset:2px;border-radius:calc(var(--_radius) * .6)}
        .rw-pick--done .rw-pick__status{color:var(--_success)}
        .rw-pick__toggle{flex:none;font-size:.8125rem;font-weight:500;color:var(--_primary)}
        .rw-pick[open] .rw-pick__toggle--show,.rw-pick:not([open]) .rw-pick__toggle--hide{display:none}
        .rw-runners{display:flex;flex-direction:column;gap:.375rem;padding:0 .5rem .5rem}
        .rw-runner{position:relative;display:flex;align-items:center;gap:.625rem;padding:.5rem .625rem;border:1px solid var(--_border);border-radius:calc(var(--_radius) * .5);cursor:pointer;transition:border-color .15s,background-color .15s}
        .rw-runner:hover{border-color:color-mix(in srgb,var(--_primary) 50%,var(--_border))}
        .rw-runner:has(:checked){border-color:var(--_primary);background:color-mix(in srgb,var(--_primary) 8%,var(--_surface))}
        .rw-runner:has(:focus-visible){outline:2px solid var(--_primary);outline-offset:2px}
        .rw-runner__input{position:absolute;opacity:0;pointer-events:none;width:1px;height:1px}
        .rw-runner__silks{flex:none;width:2.5rem;height:2.5rem;border-radius:.5rem;overflow:hidden;background:color-mix(in srgb,var(--_border) 60%,transparent)}
        .rw-runner__silks img{width:100%;height:100%;object-fit:contain;display:block}
        .rw-runner__no{flex:none;min-width:1.75rem;height:1.75rem;padding:0 .375rem;border-radius:999px;display:inline-flex;align-items:center;justify-content:center;font-size:.8125rem;font-weight:700;background:color-mix(in srgb,var(--_text) 8%,transparent)}
        .rw-runner__text{display:flex;flex-direction:column;min-width:0;flex:1}
        .rw-runner__name{font-weight:600;line-height:1.3}
        .rw-runner__meta{font-size:.8125rem;color:var(--_muted);line-height:1.35}
        .rw-runner__check{flex:none;width:1.375rem;height:1.375rem;border-radius:999px;border:2px solid var(--_border);display:inline-flex;align-items:center;justify-content:center;color:var(--_on-primary)}
        .rw-runner__check svg{width:.875rem;height:.875rem;opacity:0}
        .rw-runner:has(:checked) .rw-runner__check{background:var(--_primary);border-color:var(--_primary)}
        .rw-runner:has(:checked) .rw-runner__check svg{opacity:1}
    </style>
@endonce

<fieldset class="fb-fieldset" aria-describedby="{{ $field->hint ? $inputId.'-hint ' : '' }}{{ $inputId }}-error" @if ($error) aria-invalid="true" @endif>
    <legend class="fb-label">
        {{ $field->label }}
        @if ($field->required)
            <span class="fb-required" aria-hidden="true">*</span>
        @endif
    </legend>

    @if ($race)
        <div class="rw-race">
            @if ($race['time'] || $race['info'])
                <p class="rw-race__main">
                    @if ($race['time'])
                        <span class="rw-race__time" dir="ltr">{{ $race['time'] }}</span>
                    @endif
                    @foreach ($race['info'] as $item)
                        <bdi>{{ $item }}</bdi>
                    @endforeach
                </p>
            @endif
            @if ($race['facts'])
                <p class="rw-race__facts">
                    @foreach ($race['facts'] as $fact)
                        <span><span class="rw-muted">{{ rtrim($fact['label'], ': ') }}:</span> <bdi>{{ $fact['value'] }}</bdi></span>
                    @endforeach
                </p>
            @endif
            @if ($race['conditions'])
                <p><span class="rw-muted">{{ rtrim($race['conditions']['label'], ': ') }}:</span> <bdi>{!! nl2br(e($race['conditions']['value'])) !!}</bdi></p>
            @endif
            @if ($race['prizes'])
                <details class="rw-prizes">
                    <summary>{{ __('race_predictions.prize_money') }}</summary>
                    <ol>
                        @foreach ($race['prizes'] as $prize)
                            <li><span class="rw-muted">{{ $prize['place'] }}</span> <x-currency-price :amount="$prize['amount']" /></li>
                        @endforeach
                    </ol>
                </details>
            @endif
        </div>
    @endif

    <details @class(['rw-pick', 'rw-pick--done' => $picked !== null]) data-rw-pick data-rw-picked="{{ __('race_predictions.your_pick', ['name' => ':name']) }}" @if ($picked === null || $error !== null) open @endif>
        <summary class="rw-pick__summary">
            <span class="rw-pick__status" data-rw-status>{{ $picked === null ? __('race_predictions.choose') : $pickedText((string) $pickedRunner['no'], $choices[$picked]) }}</span>
            <span class="rw-pick__toggle rw-pick__toggle--show">{{ trans_choice('race_predictions.show_runners', count($choices), ['count' => count($choices)]) }}</span>
            <span class="rw-pick__toggle rw-pick__toggle--hide">{{ __('race_predictions.hide_runners') }}</span>
        </summary>
        <div class="rw-runners">
            @foreach ($choices as $choiceValue => $choiceLabel)
                @php
                    $runner = RaceWinnerField::runner($field, (string) $choiceValue);
                    $line = RaceWinnerField::runnerLine($runner);
                @endphp
                <label class="rw-runner" for="{{ $inputId }}-{{ $loop->index }}">
                    <input type="radio" class="rw-runner__input" id="{{ $inputId }}-{{ $loop->index }}" name="{{ $field->key }}" value="{{ $choiceValue }}"
                        data-rw-label="{{ trim(($runner['no'] ?? '').' '.$choiceLabel) }}"
                        @checked($picked === (string) $choiceValue) @if ($field->required) required @endif>
                    <span class="rw-runner__silks">
                        @if ($runner['image'])
                            <img src="{{ route('racing.image', ['p' => $runner['image']]) }}" alt="" width="40" height="40" loading="lazy">
                        @endif
                    </span>
                    @if ($runner['no'])
                        <span class="rw-runner__no">{{ $runner['no'] }}</span>
                    @endif
                    <span class="rw-runner__text">
                        <span class="rw-runner__name"><bdi>{{ $choiceLabel }}</bdi></span>
                        @if ($line !== '')
                            <span class="rw-runner__meta">{{ $line }}</span>
                        @endif
                    </span>
                    <span class="rw-runner__check" aria-hidden="true">
                        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.6l7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>
                    </span>
                </label>
            @endforeach
        </div>
    </details>
    @include('packstub-form-builder::fields._hint')
</fieldset>

@once('race-winner-script')
    <script @if (isset($nonce) && $nonce()) nonce="{{ $nonce() }}" @endif>
        // Picking a winner folds the race to "Your pick: 2 Aljasser A.s. (OM)", so the next race is in view
        // and the ones still to answer stand out.
        document.addEventListener('change', function (event) {
            var input = event.target;
            if (!input.matches || !input.matches('.rw-runner__input')) return;
            var pick = input.closest('[data-rw-pick]');
            pick.querySelector('[data-rw-status]').textContent = pick.dataset.rwPicked.replace(':name', input.dataset.rwLabel);
            pick.classList.add('rw-pick--done');
            setTimeout(function () {
                pick.open = false;
                var field = pick.closest('.fb-field') || pick;
                if (field.getBoundingClientRect().top < 0) field.scrollIntoView({ block: 'start', behavior: 'smooth' });
            }, 250);
        });
    </script>
@endonce

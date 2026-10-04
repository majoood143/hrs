{{--
    Racing silks designer. Works without JavaScript: the controls are a GET form whose query string
    is the design (SilksDesign), so "Update preview" redraws it on the server, and the SVG download
    is a plain link. resources/js/silks-designer.js repaints in place, keeps the URL in step (it is
    the share link) and adds the PNG and PDF downloads, copy link, surprise and reset buttons.
--}}
@php
    use App\Support\Localized;
    use App\Support\Silks\SilksCatalog;
    use App\Support\Silks\SilksDesign;
    use App\Support\Silks\SilksTemplate;

    $heading = Localized::value($data, 'heading') ?: __('silks.title');
    $subheading = Localized::value($data, 'subheading') ?: __('silks.subtitle');

    $catalog = SilksCatalog::load();
    $design = SilksDesign::fromQuery(request()->query(), $catalog);
    $default = SilksDesign::fromQuery([], $catalog);
    $paint = $design->paint();
    $description = $design->describe();
    $uid = 'silks-'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(6));

    $vars = collect($paint)->map(fn ($area, $name) => "--silks-{$name}-base: {$area['base']}; --silks-{$name}-accent: {$area['accent']};")->implode(' ');
    $colourRows = [['1', 'base', __('silks.main_colour'), 'heroicon-o-paint-brush'], ['2', 'accent', __('silks.pattern_colour'), 'heroicon-o-swatch']];
@endphp

<section id="silks-designer" class="mx-auto max-w-6xl scroll-mt-24 px-6 py-12" data-reveal>
    <div class="mb-8 text-center">
        <h2 class="font-display text-2xl font-semibold text-warm-900 sm:text-3xl">{{ $heading }}</h2>
        <p class="mt-2 text-warm-900/70">{{ $subheading }}</p>
    </div>

    <form method="GET" action="{{ url()->current() }}#silks-designer"
          class="grid grid-cols-1 items-start gap-8 lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)]"
          style="{{ $vars }}"
          data-silks-designer
          data-image-url="{{ route('silks.image') }}"
          data-pdf-url="{{ route('silks.pdf') }}"
          data-csrf="{{ csrf_token() }}"
          data-default="{{ json_encode($default->query()) }}"
          data-label="{{ __('silks.figure_label') }}"
          data-copied="{{ __('silks.copied') }}"
          data-failed="{{ __('silks.download_failed') }}">

        {{-- preview --}}
        <div class="card-warm p-5 sm:p-6 lg:sticky lg:top-24">
            <h3 class="flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-warm-700">
                <x-heroicon-o-eye class="h-5 w-5" aria-hidden="true" />
                {{ __('silks.preview') }}
            </h3>

            <div class="mx-auto mt-3 max-w-sm rounded-2xl bg-warm-50 p-4">
                <x-silks.figure :paint="$paint" :uid="$uid" :label="__('silks.figure_label', ['description' => $description])" class="h-auto w-full" />
            </div>

            <div class="mt-4 rounded-2xl border border-warm-200/70 p-4">
                <p class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-warm-700">
                    <x-heroicon-o-document-text class="h-4 w-4" aria-hidden="true" />
                    {{ __('silks.description') }}
                </p>
                <p class="mt-1 font-medium text-warm-900" data-silks-description aria-live="polite">{{ $description }}</p>
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                <button type="button" class="btn-warm px-5 py-2.5 text-sm" hidden data-silks-js data-silks-png>
                    <x-heroicon-o-photo class="h-5 w-5" aria-hidden="true" />
                    {{ __('silks.download_png') }}
                </button>
                <button type="button" class="btn-warm-outline px-5 py-2.5 text-sm" hidden data-silks-js data-silks-pdf>
                    <x-heroicon-o-document-arrow-down class="h-5 w-5" aria-hidden="true" />
                    {{ __('silks.download_pdf') }}
                </button>
                <a href="{{ route('silks.image', $design->query() + ['page' => url()->current(), 'download' => 1]) }}" class="btn-warm-outline px-5 py-2.5 text-sm" data-silks-svg download="racing-silks.svg">
                    <x-heroicon-o-arrow-down-tray class="h-5 w-5" aria-hidden="true" />
                    {{ __('silks.download_svg') }}
                </a>
                <button type="button" class="btn-warm-outline px-5 py-2.5 text-sm" hidden data-silks-js data-silks-copy>
                    <x-heroicon-o-link class="h-5 w-5" aria-hidden="true" />
                    <span data-silks-copy-label>{{ __('silks.copy_link') }}</span>
                </button>
            </div>

            <div class="mt-3 flex flex-wrap gap-4 text-sm font-semibold text-warm-700">
                <button type="button" class="inline-flex items-center gap-1.5 hover:text-warm-900" hidden data-silks-js data-silks-random>
                    <x-heroicon-o-sparkles class="h-5 w-5" aria-hidden="true" />
                    {{ __('silks.random') }}
                </button>
                <button type="button" class="inline-flex items-center gap-1.5 hover:text-warm-900" hidden data-silks-js data-silks-reset>
                    <x-heroicon-o-arrow-path class="h-5 w-5" aria-hidden="true" />
                    {{ __('silks.reset') }}
                </button>
            </div>

            <p class="mt-4 flex gap-2 text-xs leading-relaxed text-warm-900/60">
                <x-heroicon-o-information-circle class="h-4 w-4 shrink-0" aria-hidden="true" />
                <span>{{ __('silks.disclaimer') }}</span>
            </p>
        </div>

        {{-- controls --}}
        <div class="min-w-0">
            <div class="mb-4 flex gap-2 rounded-full bg-warm-100/70 p-1" role="tablist" hidden data-silks-js data-silks-tabs>
                @foreach(SilksTemplate::AREAS as $i => $area)
                    <button type="button" role="tab" id="{{ $uid }}-tab-{{ $area }}" aria-controls="{{ $uid }}-panel-{{ $area }}" aria-selected="{{ $i === 0 ? 'true' : 'false' }}"
                            class="flex flex-1 items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold text-warm-800 transition aria-selected:bg-white aria-selected:text-warm-900 aria-selected:shadow"
                            data-silks-tab="{{ $area }}">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-warm-600 text-xs text-white">{{ $i + 1 }}</span>
                        {{ __('silks.areas.'.$area) }}
                    </button>
                @endforeach
            </div>

            <div class="space-y-6">
                @foreach(SilksTemplate::AREAS as $area)
                    @php
                        $choice = $design->choice[$area];
                        $options = [SilksCatalog::PLAIN => ['key' => SilksCatalog::PLAIN, 'name' => $catalog->plainName(), 'svg' => '']] + $catalog->patterns[$area];
                    @endphp

                    <fieldset id="{{ $uid }}-panel-{{ $area }}" role="tabpanel" aria-labelledby="{{ $uid }}-tab-{{ $area }}" class="card-warm min-w-0 p-5 sm:p-6" data-silks-panel="{{ $area }}">
                        <legend class="sr-only">{{ __('silks.areas.'.$area) }}</legend>
                        <h3 class="font-display text-xl font-semibold text-warm-900" data-silks-panel-title>{{ __('silks.areas.'.$area) }}</h3>

                        <p class="mt-4 flex items-center gap-2 text-sm font-semibold text-warm-800">
                            <x-heroicon-o-squares-2x2 class="h-5 w-5 text-warm-600" aria-hidden="true" />
                            {{ __('silks.pattern') }}
                        </p>
                        <div class="mt-2 grid grid-cols-3 gap-2 sm:grid-cols-5 xl:grid-cols-6">
                            @foreach($options as $key => $pattern)
                                <label class="flex cursor-pointer flex-col items-center gap-1 rounded-2xl border border-warm-200 bg-white p-2 text-center text-xs font-medium leading-tight text-warm-900 transition hover:border-warm-400 has-checked:border-warm-600 has-checked:bg-warm-50 has-checked:ring-2 has-checked:ring-warm-500 has-focus-visible:ring-2 has-focus-visible:ring-accent-600">
                                    <input type="radio" name="{{ $area }}" value="{{ $key }}" class="sr-only" @checked($choice['pattern'] === $key)>
                                    <x-silks.swatch :area="$area" :svg="$pattern['svg']" :uid="$uid.'-'.$area.'-'.$key" class="h-14 w-auto" />
                                    <span>{{ $pattern['name'] }}</span>
                                </label>
                            @endforeach
                        </div>

                        @foreach($colourRows as [$suffix, $field, $label, $icon])
                            <div class="mt-5" @if($suffix === '2') data-silks-accent-row="{{ $area }}" @if($choice['pattern'] === SilksCatalog::PLAIN) hidden @endif @endif>
                                <p class="flex items-center gap-2 text-sm font-semibold text-warm-800">
                                    <x-dynamic-component :component="$icon" class="h-5 w-5 text-warm-600" aria-hidden="true" />
                                    {{ $label }}:
                                    <span class="font-normal text-warm-900/70" data-silks-name="{{ $area.$suffix }}">{{ $catalog->colors[$choice[$field]]['name'] }}</span>
                                </p>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    @foreach($catalog->colors as $color)
                                        <label class="relative cursor-pointer" title="{{ $color['name'] }}">
                                            <input type="radio" name="{{ $area.$suffix }}" value="{{ $color['key'] }}" class="peer sr-only" @checked($choice[$field] === $color['key'])>
                                            <span class="block h-9 w-9 rounded-full border border-warm-900/20 shadow-inner ring-warm-600 ring-offset-2 transition peer-checked:ring-2 peer-focus-visible:ring-2 peer-focus-visible:ring-accent-600" style="background-color: {{ $color['hex'] }}"></span>
                                            <x-heroicon-s-check class="pointer-events-none absolute inset-0 m-auto hidden h-5 w-5 peer-checked:block" style="color: {{ SilksCatalog::ink($color['hex']) }}" aria-hidden="true" />
                                            <span class="sr-only">{{ $color['name'] }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </fieldset>
                @endforeach
            </div>

            <div class="mt-6 text-center" data-silks-nojs>
                <button type="submit" class="btn-warm">
                    <x-heroicon-o-eye class="h-5 w-5" aria-hidden="true" />
                    {{ __('silks.update_preview') }}
                </button>
            </div>
        </div>

        <script type="application/json" data-silks-catalog>@json($catalog->forScript())</script>
    </form>
</section>

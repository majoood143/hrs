@php
    $tree = $stallion->pedigreeTree();
    // the bracket: parents span 4 rows, grandparents 2, great-grandparents 1 (sire's half on top)
    $columns = [
        ['sire' => 4, 'dam' => 4],
        ['sire_sire' => 2, 'sire_dam' => 2, 'dam_sire' => 2, 'dam_dam' => 2],
        array_fill_keys(array_slice(\App\Models\Stallion::PEDIGREE_KEYS, 4), 1),
    ];
    $spans = [4 => 'row-span-4', 2 => 'row-span-2', 1 => 'row-span-1'];
    $hasTree = count(array_filter($tree)) > 2;
    $labels = ['sire' => __('stallions.sire'), 'dam' => __('stallions.dam')] + __('stallions.ancestors');
@endphp

<x-layouts.site :seo-title="$seoTitle" :seo-description="$seoDescription" :seo-image="$seoImage">
    <div class="mx-auto max-w-5xl px-6 py-14">
        <a href="{{ route('stallions.index') }}" class="text-sm font-semibold text-warm-600 hover:text-warm-800">
            <span aria-hidden="true" class="inline-block rtl:rotate-180">&larr;</span> {{ __('stallions.back_to_listing') }}
        </a>

        <div class="mt-6 grid gap-8 lg:grid-cols-5">
            <div class="lg:col-span-3">
                @include('site.stallions._photo', ['stallion' => $stallion, 'class' => 'h-72 w-full rounded-3xl sm:h-96'])
            </div>

            <div class="lg:col-span-2">
                <h1 class="font-display text-4xl font-semibold text-warm-900 sm:text-5xl">{{ $stallion->name }}</h1>
                @if($stallion->sire || $stallion->dam)
                    <p class="mt-3 font-display text-xl text-warm-800">{{ __('stallions.by', ['sire' => $stallion->sire, 'dam' => $stallion->dam]) }}</p>
                @endif
                @include('site.stallions._facts', ['stallion' => $stallion, 'class' => 'mt-2 text-sm text-warm-900/60'])
                @include('site.stallions._record', ['stallion' => $stallion, 'class' => 'mt-5 text-base'])

                <div class="mt-6 space-y-1 text-sm text-warm-900/70">
                    @if($stallion->stud)
                        <p>{{ __('stallions.stud') }}: <span class="font-semibold text-warm-900">{{ $stallion->stud }}</span></p>
                    @endif
                    @if($stallion->city)
                        <p>{{ __('stallions.city') }}: <span class="font-semibold text-warm-900">{{ $stallion->city->name }}</span></p>
                    @endif
                </div>

                @if($stallion->contact_number)
                    <button type="button" data-reveal-contact="{{ $stallion->contact_number }}" class="js-reveal mt-6 flex w-full items-center justify-center rounded-full bg-warm-900 px-4 py-3 text-sm font-bold text-white">
                        {{ __('stallions.contact') }}
                    </button>
                @endif
            </div>
        </div>

        @if($hasTree || $stallion->pedigree_url)
            <section class="mt-14" aria-labelledby="pedigree-title">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <h2 id="pedigree-title" class="font-display text-2xl font-semibold text-warm-900">{{ __('stallions.pedigree') }}</h2>
                    @if($stallion->pedigree_url)
                        <a href="{{ $stallion->pedigree_url }}" target="_blank" rel="noopener" class="btn-warm-outline !py-2 text-sm">{{ __('stallions.full_pedigree') }}</a>
                    @endif
                </div>

                @if($hasTree)
                    <div class="mt-5 grid grid-flow-col grid-cols-3 grid-rows-8 gap-1.5 text-xs sm:text-sm">
                        @foreach($columns as $column)
                            @foreach($column as $key => $span)
                                @php $sireSide = str_starts_with($key, 'sire'); @endphp
                                <div class="{{ $spans[$span] }} flex flex-col justify-center rounded-lg border-s-4 px-2 py-1.5 sm:px-3 {{ $sireSide ? 'border-warm-700 bg-warm-100' : 'border-warm-300 bg-white' }}">
                                    <span class="text-[10px] text-warm-900/50 sm:text-xs">{{ $labels[$key] }}</span>
                                    <span class="{{ $span === 4 ? 'font-display text-base font-semibold sm:text-lg' : 'font-semibold' }} text-warm-900">{{ $tree[$key] ?? '—' }}</span>
                                </div>
                            @endforeach
                        @endforeach
                    </div>
                @endif
            </section>
        @endif

        @if($stallion->highlights || $stallion->racing_horse_id)
            <section class="mt-14 grid gap-6 lg:grid-cols-3">
                @if($stallion->highlights)
                    <div class="lg:col-span-2">
                        <h2 class="font-display text-2xl font-semibold text-warm-900">{{ __('stallions.highlights') }}</h2>
                        <p class="mt-3 whitespace-pre-line leading-relaxed text-warm-900/80">{{ $stallion->highlights }}</p>
                    </div>
                @endif
                @if($stallion->racing_horse_id)
                    <div class="card-warm p-5">
                        <h2 class="font-display text-xl font-semibold text-warm-900">{{ __('stallions.race_record') }}</h2>
                        <p class="mt-2 text-sm text-warm-900/70">{{ __('stallions.racing_record_body') }}</p>
                        <a href="{{ route('racing.profile', ['entity' => 'horse', 'id' => $stallion->racing_horse_id]) }}" class="btn-warm mt-4 inline-flex">{{ __('stallions.racing_record_link') }}</a>
                    </div>
                @endif
            </section>
        @endif

        <div class="mt-14">
            <x-share-buttons :url="url()->current()" :title="$stallion->name" />
        </div>
    </div>
</x-layouts.site>

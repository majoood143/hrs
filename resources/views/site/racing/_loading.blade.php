{{-- Shown by resources/js/racing-search.js while a search (or a results page) is loading. --}}
<div hidden data-racing-loading data-steps='@json(__('racing.loading_steps'))'
     class="mt-8" role="status" aria-live="polite" aria-label="{{ __('racing.loading_label') }}">
    <div class="h-1 overflow-hidden rounded-full bg-warm-100">
        <div class="racing-progress h-full w-1/3 rounded-full bg-warm-500"></div>
    </div>

    <p class="mt-4 flex items-center gap-2 text-sm font-medium text-warm-800">
        <x-heroicon-o-circle-stack class="h-5 w-5 shrink-0 animate-pulse text-warm-500 motion-reduce:animate-none" aria-hidden="true" />
        <span data-racing-loading-step>{{ __('racing.loading_steps')[0] }}</span>
    </p>

    <div class="mt-5 animate-pulse motion-reduce:animate-none" aria-hidden="true">
        <div class="h-5 w-40 rounded-md bg-warm-200/80"></div>

        <div class="mt-3 overflow-hidden rounded-2xl border border-warm-200/70 bg-white shadow-sm shadow-warm-900/5">
            <div class="flex gap-6 bg-warm-50 px-4 py-3">
                <div class="h-3 w-1/4 rounded bg-warm-200"></div>
                <div class="h-3 w-1/6 rounded bg-warm-200"></div>
                <div class="h-3 w-1/6 rounded bg-warm-200"></div>
                <div class="hidden h-3 w-1/6 rounded bg-warm-200 sm:block"></div>
            </div>
            <div class="divide-y divide-warm-100">
                @foreach([['w-2/5', 'w-1/5', 'w-1/6'], ['w-1/3', 'w-1/4', 'w-1/5'], ['w-1/2', 'w-1/6', 'w-1/4'], ['w-1/4', 'w-1/5', 'w-1/6'], ['w-2/5', 'w-1/4', 'w-1/5']] as $widths)
                    <div class="flex items-center gap-6 px-4 py-4">
                        <div class="h-3.5 {{ $widths[0] }} rounded bg-warm-200/90"></div>
                        <div class="h-3 {{ $widths[1] }} rounded bg-warm-100"></div>
                        <div class="h-3 {{ $widths[2] }} rounded bg-warm-100"></div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

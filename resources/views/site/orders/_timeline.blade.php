{{-- The customer's view of the order's progress: newest facts from OrderEvent::label(), in the viewer's language. --}}
@if($events->isNotEmpty())
    <h2 class="mt-10 flex items-center gap-2 font-display text-xl font-semibold text-warm-900">
        <x-heroicon-o-clock class="h-6 w-6 text-warm-600" aria-hidden="true" />
        {{ __('orders.timeline') }}
    </h2>
    <ol class="card-warm mt-4 p-6 text-sm">
        @foreach($events as $event)
            <li class="relative flex gap-4 {{ $loop->last ? '' : 'pb-6' }}">
                @unless($loop->last)
                    <span class="absolute top-10 bottom-0 w-0.5 bg-warm-200" style="inset-inline-start: 1.1875rem" aria-hidden="true"></span>
                @endunless
                <span class="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-full {{ $loop->last ? 'bg-warm-600 text-white shadow-md shadow-warm-600/30' : 'bg-warm-100 text-warm-700' }}">
                    <x-dynamic-component :component="$event->icon()" class="h-5 w-5" aria-hidden="true" />
                </span>
                <div class="pt-1">
                    <p class="font-medium text-warm-900">{{ $event->label() }}</p>
                    <p class="text-xs text-warm-700" dir="ltr">{{ $event->created_at->format('Y-m-d H:i') }}</p>
                </div>
            </li>
        @endforeach
    </ol>
@endif

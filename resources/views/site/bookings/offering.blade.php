@php
    $selected = \Carbon\CarbonImmutable::parse($date)->locale(app()->getLocale());
    $money = fn ($baisa) => \App\Models\SiteSetting::formatCurrencyHtml($baisa / 1000, 3);
@endphp

<x-layouts.site :seo-title="$seoTitle" :seo-description="$seoDescription ?? null" :seo-image="$seoImage ?? null">
    <div class="mx-auto max-w-5xl px-6 py-14">
        <a href="{{ route('stables.show', $stable->slug) }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-warm-600 hover:text-warm-800">
            <x-heroicon-o-arrow-left class="h-4 w-4 rtl:rotate-180" aria-hidden="true" />
            {{ $stable->name }}
        </a>

        <div class="mt-6 grid gap-8 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <p class="section-eyebrow flex items-center gap-1.5">
                    <x-dynamic-component :component="$offering->type->icon()" class="h-4 w-4" aria-hidden="true" />
                    {{ $offering->type->label() }}
                </p>
                <h1 class="mt-2 font-display text-3xl font-semibold text-warm-900">{{ $offering->name }}</h1>
                <p class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-warm-700">
                    <span class="inline-flex items-center gap-1"><x-heroicon-o-clock class="h-4 w-4" aria-hidden="true" />{{ trans_choice('stable_panel.units.minutes', $offering->duration_minutes, ['count' => $offering->duration_minutes]) }}</span>
                    <span class="inline-flex items-center gap-1"><x-heroicon-o-user-group class="h-4 w-4" aria-hidden="true" />{{ $offering->capacity === 1 ? __('stable_bookings.private') : trans_choice('stable_bookings.group_of', $offering->capacity, ['count' => $offering->capacity]) }}</span>
                    <span class="inline-flex items-center gap-1"><x-heroicon-o-map-pin class="h-4 w-4" aria-hidden="true" />{{ $stable->city?->name }}</span>
                </p>

                @if($offering->photo_url)
                    <img src="{{ $offering->photo_url }}" alt="" class="mt-6 h-64 w-full rounded-3xl object-cover">
                @endif

                @if($offering->description)
                    <p class="mt-6 whitespace-pre-line text-sm leading-relaxed text-warm-900/80">{{ $offering->description }}</p>
                @endif

                @if(session('error'))
                    <div class="mt-6 flex gap-3 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800" role="alert">
                        <x-heroicon-o-exclamation-triangle class="h-5 w-5 shrink-0" aria-hidden="true" />
                        <p>{{ session('error') }}</p>
                    </div>
                @endif

                <h2 class="mt-10 flex items-center gap-2 font-display text-xl font-semibold text-warm-900">
                    <x-heroicon-o-calendar-days class="h-6 w-6 text-warm-600" aria-hidden="true" />
                    {{ __('stable_bookings.offering.pick_day') }}
                </h2>

                @if($days->isEmpty())
                    <p class="card-warm mt-4 p-6 text-sm text-warm-700">{{ __('stable_bookings.offering.no_days') }}</p>
                @else
                    <nav class="mt-4 flex gap-2 overflow-x-auto pb-2" aria-label="{{ __('stable_bookings.offering.pick_day') }}">
                        @foreach($days as $day => $count)
                            @php $d = \Carbon\CarbonImmutable::parse($day)->locale(app()->getLocale()); @endphp
                            <a href="{{ route('bookings.offering', ['stable' => $stable->slug, 'offering' => $offering->id, 'date' => $day, 'riders' => $riders]) }}"
                               @class([
                                   'flex min-w-20 shrink-0 flex-col items-center rounded-2xl border px-3 py-2 text-center transition',
                                   'border-warm-600 bg-warm-600 text-white' => $day === $date,
                                   'border-warm-200 bg-white text-warm-900 hover:border-warm-400' => $day !== $date,
                               ])
                               @if($day === $date) aria-current="date" @endif>
                                <span class="text-xs font-semibold uppercase">{{ $d->translatedFormat('D') }}</span>
                                <span class="font-display text-xl font-semibold">{{ $d->format('j') }}</span>
                                <span class="text-[11px]">{{ $d->translatedFormat('M') }}</span>
                            </a>
                        @endforeach
                    </nav>

                    <h3 class="mt-6 text-sm font-semibold text-warm-900">{{ $selected->translatedFormat('l j F Y') }}</h3>
                    @if($slots->isEmpty())
                        <p class="mt-2 text-sm text-warm-700">{{ __('stable_bookings.offering.day_full') }}</p>
                    @else
                        <ul class="mt-3 grid gap-3 sm:grid-cols-2">
                            @foreach($slots as $slot)
                                <li class="card-warm flex items-center justify-between gap-3 p-4">
                                    <div>
                                        <p class="flex items-center gap-1.5 font-semibold text-warm-900">
                                            <x-heroicon-o-clock class="h-5 w-5 text-warm-600" aria-hidden="true" />
                                            <span dir="ltr">{{ $slot->timeRange() }}</span>
                                        </p>
                                        <p class="mt-1 text-xs text-warm-700">
                                            {{ trans_choice('stable_bookings.places_left', $slot->remainingPlaces(), ['count' => $slot->remainingPlaces()]) }}
                                            @if($slot->trainer) · {{ __('stable_bookings.with_trainer', ['name' => $slot->trainer->name]) }} @endif
                                        </p>
                                    </div>
                                    <a href="{{ route('bookings.create', ['slot' => $slot->id, 'riders' => $riders]) }}" class="btn-warm !px-4 !py-2 text-sm">
                                        {{ __('stable_bookings.book') }}
                                        <x-heroicon-o-arrow-right class="h-4 w-4 rtl:rotate-180" aria-hidden="true" />
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                @endif
            </div>

            <aside class="space-y-4">
                <div class="card-warm p-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-warm-700">{{ __('stable_bookings.price') }}</p>
                    <p class="mt-1 font-display text-2xl font-semibold text-warm-900">{!! \App\Models\SiteSetting::formatCurrencyHtml($offering->price, 3) !!}</p>
                    <p class="text-sm text-warm-700">{{ __('stable_bookings.per_rider') }}</p>

                    @if($offering->max_riders > 1)
                        <form method="GET" class="mt-4">
                            <input type="hidden" name="date" value="{{ $date }}">
                            <label for="riders" class="text-xs font-semibold uppercase tracking-wide text-warm-700">{{ __('stable_bookings.search.riders') }}</label>
                            <select id="riders" name="riders" onchange="this.form.submit()" class="mt-1 w-full rounded-xl border border-warm-200 bg-white px-3 py-2 text-sm text-warm-900">
                                @foreach(range($offering->min_riders, $offering->max_riders) as $n)
                                    <option value="{{ $n }}" @selected($riders === $n)>{{ trans_choice('stable_bookings.riders_count', $n, ['count' => $n]) }}</option>
                                @endforeach
                            </select>
                            <noscript><button type="submit" class="btn-warm-outline mt-2 w-full justify-center !py-2 text-sm">{{ __('stable_bookings.update') }}</button></noscript>
                        </form>
                    @endif

                    @unless($quote->isFree())
                        <dl class="mt-4 space-y-1 border-t border-warm-100 pt-4 text-sm">
                            <div class="flex justify-between"><dt class="text-warm-700">{{ trans_choice('stable_bookings.riders_count', $riders, ['count' => $riders]) }}</dt><dd dir="ltr">{!! $money($quote->price) !!}</dd></div>
                            @if($quote->fee > 0)
                                <div class="flex justify-between"><dt class="text-warm-700">{{ __('orders.service_fee') }}</dt><dd dir="ltr">{!! $money($quote->fee) !!}</dd></div>
                            @endif
                            @if($quote->vatOnPrice + $quote->vatOnFee > 0)
                                <div class="flex justify-between"><dt class="text-warm-700">{{ __('orders.vat', ['rate' => rtrim(rtrim(number_format($quote->vatRate, 2), '0'), '.')]) }}</dt><dd dir="ltr">{!! $money($quote->vatOnPrice + $quote->vatOnFee) !!}</dd></div>
                            @endif
                            <div class="flex justify-between pt-1 font-semibold text-warm-900"><dt>{{ __('orders.total') }}</dt><dd dir="ltr">{!! $money($quote->total()) !!}</dd></div>
                        </dl>
                    @endunless
                </div>

                @php $packages = $offering->stable->packages()->active()->where('stable_offering_id', $offering->id)->orderBy('sessions')->get(); @endphp
                @if($packages->isNotEmpty())
                    <div class="card-warm p-5">
                        <p class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-warm-700">
                            <x-heroicon-o-rectangle-stack class="h-4 w-4" aria-hidden="true" />{{ __('stable_packages.eyebrow') }}
                        </p>
                        <ul class="mt-3 space-y-3">
                            @foreach($packages as $package)
                                <li class="rounded-xl border border-warm-200 p-3">
                                    <p class="font-semibold text-warm-900">{{ $package->name }}</p>
                                    <p class="text-sm text-warm-700">
                                        {{ trans_choice('stable_packages.sessions_count', $package->sessions, ['count' => $package->sessions]) }}
                                        · {!! \App\Models\SiteSetting::formatCurrencyHtml($package->price, 3) !!}
                                    </p>
                                    @if($package->savingBaisa() > 0)
                                        <p class="text-xs font-semibold text-emerald-700">{{ __('stable_packages.saving', ['amount' => \App\Support\Money::format($package->savingBaisa())]) }}</p>
                                    @endif
                                    <a href="{{ route('account.packages.buy', $package) }}" class="btn-warm-outline mt-2 w-full justify-center !py-1.5 text-sm">
                                        <x-heroicon-o-shopping-bag class="h-4 w-4" aria-hidden="true" />{{ __('stable_packages.buy') }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                        <p class="mt-3 text-xs text-warm-700">{{ __('stable_packages.sign_in_note') }}</p>
                    </div>
                @endif

                @php $hours = $stable->bookingSettings()->cancellationHours(); @endphp
                <div class="card-warm flex gap-3 p-5 text-sm text-warm-700">
                    <x-heroicon-o-arrow-uturn-left class="h-5 w-5 shrink-0 text-warm-600" aria-hidden="true" />
                    <p>{{ $hours === null ? __('stable_bookings.policy.no_online_cancel') : __('stable_bookings.policy.cancel_until', ['hours' => $hours]) }}</p>
                </div>
            </aside>
        </div>
    </div>
</x-layouts.site>

@php
    $field = 'mt-1 w-full rounded-xl border border-warm-200 bg-white px-4 py-2.5 text-sm text-warm-900 focus:border-warm-500 focus:outline-none';
    $label = 'block text-sm font-semibold text-warm-900';
    $money = fn ($baisa) => \App\Models\SiteSetting::formatCurrencyHtml($baisa / 1000, 3);
    $start = $slot->startsAt()->locale(app()->getLocale());
    $options = $settings->paymentOptions();
    $req = fn (string $name) => $settings->requiresRiderField($name);
@endphp

<x-layouts.site :seo-title="$seoTitle" :noindex="true">
    <div class="mx-auto max-w-5xl px-6 py-14">
        <a href="{{ route('bookings.offering', ['stable' => $stable->slug, 'offering' => $offering->id, 'date' => $slot->date->toDateString(), 'riders' => $riders]) }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-warm-600 hover:text-warm-800">
            <x-heroicon-o-arrow-left class="h-4 w-4 rtl:rotate-180" aria-hidden="true" />
            {{ __('stable_bookings.form.change_time') }}
        </a>

        <p class="section-eyebrow mt-6">{{ __('stable_bookings.form.eyebrow') }}</p>
        <h1 class="mt-2 font-display text-3xl font-semibold text-warm-900">{{ __('stable_bookings.form.title') }}</h1>

        @if(session('error'))
            <div class="mt-6 flex gap-3 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800" role="alert">
                <x-heroicon-o-exclamation-triangle class="h-5 w-5 shrink-0" aria-hidden="true" />
                <p>{{ session('error') }}</p>
            </div>
        @endif
        @if($errors->any())
            <div class="mt-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                <p class="flex items-center gap-2 font-semibold"><x-heroicon-o-exclamation-triangle class="h-5 w-5" aria-hidden="true" />{{ __('stable_bookings.form.check_errors') }}</p>
                <ul class="mt-1 list-inside list-disc">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('bookings.store', $slot) }}" class="mt-8 grid gap-8 lg:grid-cols-3">
            @csrf
            <input type="hidden" name="riders_count" value="{{ $riders }}">

            <div class="space-y-6 lg:col-span-2">
                {{-- the riders --}}
                @foreach(range(0, $riders - 1) as $i)
                    <fieldset class="card-warm space-y-4 p-6">
                        <legend class="flex items-center gap-2 font-display text-lg font-semibold text-warm-900">
                            <x-heroicon-o-user class="h-5 w-5 text-warm-600" aria-hidden="true" />
                            {{ $riders > 1 ? __('stable_bookings.form.rider_n', ['n' => $i + 1]) : __('stable_bookings.form.rider') }}
                        </legend>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label for="rider-{{ $i }}-name" class="{{ $label }}">{{ __('stable_bookings.rider.name') }} *</label>
                                <input id="rider-{{ $i }}-name" name="riders[{{ $i }}][name]" value="{{ old("riders.$i.name", $i === 0 ? $customer?->name : null) }}" required maxlength="120" class="{{ $field }}">
                            </div>
                            @if($settings->showsRiderField('age'))
                                <div>
                                    <label for="rider-{{ $i }}-age" class="{{ $label }}">{{ __('stable_bookings.rider.age') }}{{ $req('age') ? ' *' : '' }}</label>
                                    <input id="rider-{{ $i }}-age" name="riders[{{ $i }}][age]" type="number" min="2" max="100" inputmode="numeric" value="{{ old("riders.$i.age") }}" @required($req('age')) class="{{ $field }}" dir="ltr">
                                </div>
                            @endif
                            @if($settings->showsRiderField('level'))
                                <div>
                                    <label for="rider-{{ $i }}-level" class="{{ $label }}">{{ __('stable_bookings.rider.level') }}{{ $req('level') ? ' *' : '' }}</label>
                                    <select id="rider-{{ $i }}-level" name="riders[{{ $i }}][level]" @required($req('level')) class="{{ $field }}">
                                        <option value="">—</option>
                                        @foreach(\App\Support\StableBookingSettings::LEVELS as $level)
                                            <option value="{{ $level }}" @selected(old("riders.$i.level") === $level)>{{ __('stable_bookings.levels.'.$level) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                            @if($settings->showsRiderField('weight'))
                                <div>
                                    <label for="rider-{{ $i }}-weight" class="{{ $label }}">{{ __('stable_bookings.rider.weight') }}{{ $req('weight') ? ' *' : '' }}</label>
                                    <input id="rider-{{ $i }}-weight" name="riders[{{ $i }}][weight]" type="number" min="10" max="250" step="0.5" inputmode="decimal" value="{{ old("riders.$i.weight") }}" @required($req('weight')) class="{{ $field }}" dir="ltr">
                                </div>
                            @endif
                            @if($settings->showsRiderField('height'))
                                <div>
                                    <label for="rider-{{ $i }}-height" class="{{ $label }}">{{ __('stable_bookings.rider.height') }}{{ $req('height') ? ' *' : '' }}</label>
                                    <input id="rider-{{ $i }}-height" name="riders[{{ $i }}][height]" type="number" min="50" max="250" inputmode="numeric" value="{{ old("riders.$i.height") }}" @required($req('height')) class="{{ $field }}" dir="ltr">
                                </div>
                            @endif
                            @if($settings->showsRiderField('guardian'))
                                <div>
                                    <label for="rider-{{ $i }}-guardian-name" class="{{ $label }}">{{ __('stable_bookings.rider.guardian_name') }}{{ $req('guardian') ? ' *' : '' }}</label>
                                    <input id="rider-{{ $i }}-guardian-name" name="riders[{{ $i }}][guardian_name]" value="{{ old("riders.$i.guardian_name") }}" @required($req('guardian')) maxlength="120" class="{{ $field }}">
                                </div>
                                <div>
                                    <label for="rider-{{ $i }}-guardian-phone" class="{{ $label }}">{{ __('stable_bookings.rider.guardian_phone') }}{{ $req('guardian') ? ' *' : '' }}</label>
                                    <input id="rider-{{ $i }}-guardian-phone" name="riders[{{ $i }}][guardian_phone]" type="tel" value="{{ old("riders.$i.guardian_phone") }}" @required($req('guardian')) maxlength="20" class="{{ $field }}" dir="ltr">
                                </div>
                            @endif
                            @if($settings->showsRiderField('notes'))
                                <div class="sm:col-span-2">
                                    <label for="rider-{{ $i }}-notes" class="{{ $label }}">{{ __('stable_bookings.rider.notes') }}{{ $req('notes') ? ' *' : '' }}</label>
                                    <textarea id="rider-{{ $i }}-notes" name="riders[{{ $i }}][notes]" rows="2" maxlength="500" @required($req('notes')) class="{{ $field }}">{{ old("riders.$i.notes") }}</textarea>
                                </div>
                            @endif
                        </div>
                    </fieldset>
                @endforeach

                {{-- who to contact --}}
                <fieldset class="card-warm space-y-4 p-6">
                    <legend class="flex items-center gap-2 font-display text-lg font-semibold text-warm-900">
                        <x-heroicon-o-phone class="h-5 w-5 text-warm-600" aria-hidden="true" />
                        {{ __('stable_bookings.form.contact') }}
                    </legend>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label for="name" class="{{ $label }}">{{ __('stable_bookings.form.your_name') }} *</label>
                            <input id="name" name="name" value="{{ old('name', $customer?->name) }}" required maxlength="120" autocomplete="name" class="{{ $field }}">
                        </div>
                        <div>
                            <label for="phone" class="{{ $label }}">{{ __('account.phone_label') }} *</label>
                            <input id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" value="{{ old('phone', $customer?->phone) }}" required maxlength="20" placeholder="9123 4567" class="{{ $field }}" dir="ltr">
                            <p class="mt-1 text-xs text-warm-700">{{ __('stable_bookings.form.phone_hint') }}</p>
                        </div>
                        <div>
                            <label for="email" class="{{ $label }}">{{ __('stable_bookings.form.email') }}</label>
                            <input id="email" name="email" type="email" autocomplete="email" value="{{ old('email', $customer?->email) }}" maxlength="190" class="{{ $field }}" dir="ltr">
                        </div>
                    </div>
                </fieldset>

                @unless($quote->isFree())
                    <fieldset class="card-warm space-y-3 p-6">
                        @foreach($packages as $purchase)
                            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-warm-200 p-4 has-[:checked]:border-warm-600 has-[:checked]:bg-warm-50">
                                <input type="radio" name="payment_option" value="package" class="mt-1" required
                                       @checked(old('payment_option', 'package') === 'package' && (int) old('package_purchase_id', $packages->first()->id) === $purchase->id)
                                       onchange="document.getElementById('package_purchase_id').value = '{{ $purchase->id }}'">
                                <span>
                                    <span class="flex items-center gap-1.5 font-semibold text-warm-900">
                                        <x-heroicon-o-rectangle-stack class="h-5 w-5" aria-hidden="true" />
                                        {{ __('stable_packages.use_package', ['name' => $purchase->package?->name ?? $purchase->name]) }}
                                    </span>
                                    <span class="mt-0.5 block text-sm text-warm-700">{{ trans_choice('stable_packages.left_until', $purchase->sessionsLeft(), ['count' => $purchase->sessionsLeft(), 'date' => $purchase->expires_at->toDateString()]) }}</span>
                                </span>
                            </label>
                        @endforeach
                        @if($packages->isNotEmpty())
                            <input type="hidden" id="package_purchase_id" name="package_purchase_id" value="{{ old('package_purchase_id', $packages->first()->id) }}">
                        @endif
                        <legend class="flex items-center gap-2 font-display text-lg font-semibold text-warm-900">
                            <x-heroicon-o-credit-card class="h-5 w-5 text-warm-600" aria-hidden="true" />
                            {{ __('stable_bookings.form.payment') }}
                        </legend>
                        @foreach($options as $option)
                            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-warm-200 p-4 has-[:checked]:border-warm-600 has-[:checked]:bg-warm-50">
                                <input type="radio" name="payment_option" value="{{ $option }}" class="mt-1" @checked(old('payment_option', $packages->isNotEmpty() ? 'package' : $options[0]) === $option) required>
                                <span>
                                    <span class="flex items-center gap-1.5 font-semibold text-warm-900">
                                        <x-dynamic-component :component="$option === 'online' ? 'heroicon-o-credit-card' : 'heroicon-o-building-storefront'" class="h-5 w-5" aria-hidden="true" />
                                        {{ __('stable_bookings.payment_options.'.$option) }}
                                    </span>
                                    <span class="mt-0.5 block text-sm text-warm-700">{{ __('stable_bookings.payment_options.'.$option.'_hint') }}</span>
                                </span>
                            </label>
                        @endforeach
                    </fieldset>
                @endunless

                @if($settings->showsRiderField('waiver'))
                    <div class="card-warm p-6">
                        <h2 class="flex items-center gap-2 font-display text-lg font-semibold text-warm-900">
                            <x-heroicon-o-shield-check class="h-5 w-5 text-warm-600" aria-hidden="true" />
                            {{ __('stable_bookings.form.waiver') }}
                        </h2>
                        <div class="mt-3 max-h-48 overflow-y-auto whitespace-pre-line rounded-xl bg-warm-50 p-4 text-sm text-warm-800">{{ $settings->waiverText() }}</div>
                        <label class="mt-3 flex items-start gap-2 text-sm font-medium text-warm-900">
                            <input type="checkbox" name="waiver" value="1" required class="mt-1" @checked(old('waiver'))>
                            {{ __('stable_bookings.form.waiver_accept') }}
                        </label>
                    </div>
                @endif
            </div>

            <aside class="space-y-4">
                <div class="card-warm p-5 lg:sticky lg:top-6">
                    <p class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-warm-700">
                        <x-heroicon-o-clipboard-document-list class="h-4 w-4" aria-hidden="true" />{{ __('stable_bookings.form.summary') }}
                    </p>
                    <p class="mt-2 font-display text-lg font-semibold text-warm-900">{{ $offering->name }}</p>
                    <p class="text-sm text-warm-700">{{ $stable->name }}</p>
                    <ul class="mt-3 space-y-1.5 text-sm text-warm-900">
                        <li class="flex items-center gap-2"><x-heroicon-o-calendar class="h-4 w-4 text-warm-600" aria-hidden="true" />{{ $start->translatedFormat('l j F Y') }}</li>
                        <li class="flex items-center gap-2"><x-heroicon-o-clock class="h-4 w-4 text-warm-600" aria-hidden="true" /><span dir="ltr">{{ $slot->timeRange() }}</span></li>
                        <li class="flex items-center gap-2"><x-heroicon-o-user-group class="h-4 w-4 text-warm-600" aria-hidden="true" />{{ trans_choice('stable_bookings.riders_count', $riders, ['count' => $riders]) }}</li>
                    </ul>

                    <dl class="mt-4 space-y-1 border-t border-warm-100 pt-4 text-sm">
                        @if($quote->isFree())
                            <div class="flex justify-between font-semibold text-warm-900"><dt>{{ __('orders.total') }}</dt><dd>{{ __('stable_bookings.free') }}</dd></div>
                        @else
                            <div class="flex justify-between"><dt class="text-warm-700">{{ $offering->name }}</dt><dd dir="ltr">{!! $money($quote->price) !!}</dd></div>
                            @if($quote->fee > 0)
                                <div class="flex justify-between"><dt class="text-warm-700">{{ __('orders.service_fee') }}</dt><dd dir="ltr">{!! $money($quote->fee) !!}</dd></div>
                            @endif
                            @if($quote->vatOnPrice + $quote->vatOnFee > 0)
                                <div class="flex justify-between"><dt class="text-warm-700">{{ __('orders.vat', ['rate' => rtrim(rtrim(number_format($quote->vatRate, 2), '0'), '.')]) }}</dt><dd dir="ltr">{!! $money($quote->vatOnPrice + $quote->vatOnFee) !!}</dd></div>
                            @endif
                            <div class="flex justify-between pt-1 text-base font-semibold text-warm-900"><dt>{{ __('orders.total') }}</dt><dd dir="ltr">{!! $money($quote->total()) !!}</dd></div>
                        @endif
                    </dl>

                    <button type="submit" class="btn-warm mt-5 w-full justify-center">
                        <x-heroicon-o-check-circle class="h-5 w-5" aria-hidden="true" />
                        {{ __('stable_bookings.form.submit') }}
                    </button>

                    @php $hours = $settings->cancellationHours(); @endphp
                    <p class="mt-3 flex gap-2 text-xs text-warm-700">
                        <x-heroicon-o-arrow-uturn-left class="h-4 w-4 shrink-0" aria-hidden="true" />
                        {{ $hours === null ? __('stable_bookings.policy.no_online_cancel') : __('stable_bookings.policy.cancel_until', ['hours' => $hours]) }}
                    </p>
                </div>
            </aside>
        </form>
    </div>
</x-layouts.site>

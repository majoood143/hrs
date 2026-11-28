{{--
    The promo code box of the booking and package pages, placed inside the page's POST form.
    - With `apply-to` (the booking form): the input is the POST form's own `promo_code`, and Apply posts
      the whole form there with `apply_promo=1`; the controller sends it back with what was typed and
      the code applied, booking nothing.
    - Without (the package page, no fields to lose): the input and Apply belong to the page's empty
      `<form id="promo-code" method="GET">` (outside the POST form: forms cannot nest), which reloads
      the page with ?promo_code=; the POST form gets the applied code in a hidden `promo_code` field.
--}}
@props(['query' => [], 'code' => null, 'error' => null, 'discount' => 0, 'applyTo' => null])
@php
    $applied = filled($code) && ! $error && $discount > 0;
@endphp
@foreach($query as $name => $value)
    <input type="hidden" form="promo-code" name="{{ $name }}" value="{{ $value }}">
@endforeach
<div class="mt-4 border-t border-warm-100 pt-4">
    <label for="promo_code_input" class="flex items-center gap-1.5 text-sm font-semibold text-warm-900">
        <x-heroicon-o-ticket class="h-4 w-4 text-warm-600" aria-hidden="true" />{{ __('promo_codes.site.label') }}
    </label>
    <div class="mt-1 flex gap-2">
        <input id="promo_code_input" {{ $applyTo ? '' : 'form=promo-code' }} name="promo_code" value="{{ $code }}" maxlength="50" dir="ltr" autocomplete="off"
               placeholder="{{ __('promo_codes.site.placeholder') }}"
               class="w-full min-w-0 rounded-xl border border-warm-200 bg-white px-3 py-2 text-sm uppercase text-warm-900 focus:border-warm-500 focus:outline-none">
        <button type="submit" class="btn-warm-outline shrink-0 px-3 py-2 text-sm"
                @if($applyTo) formaction="{{ $applyTo }}" name="apply_promo" value="1" formnovalidate @else form="promo-code" @endif>
            <x-heroicon-o-check class="h-4 w-4" aria-hidden="true" />{{ __('promo_codes.site.apply') }}
        </button>
    </div>
    @if($error)
        <p class="mt-1.5 flex items-center gap-1.5 text-sm text-red-700" role="alert"><x-heroicon-o-exclamation-circle class="h-4 w-4 shrink-0" aria-hidden="true" />{{ $error }}</p>
    @elseif($applied)
        <p class="mt-1.5 flex items-center gap-1.5 text-sm font-medium text-emerald-700"><x-heroicon-o-check-badge class="h-4 w-4 shrink-0" aria-hidden="true" />{{ __('promo_codes.site.applied') }}</p>
    @endif
    @if($applied && ! $applyTo)
        <input type="hidden" name="promo_code" value="{{ $code }}">
    @endif
</div>

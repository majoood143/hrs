@php
    $input = 'mt-1 w-full rounded-xl border border-warm-200 bg-warm-50 px-3 py-2.5 text-sm text-warm-900 focus:border-warm-500 focus:outline-none focus:ring-2 focus:ring-warm-300';
    $label = 'text-sm font-semibold text-warm-900';
    $field = fn (string $name) => __('auctions.sell.fields.'.$name);
    $placeholder = fn (string $name) => __('auctions.sell.placeholders.'.$name);
@endphp

<x-layouts.site :seo-title="$seoTitle" :seo-description="$seoDescription">
    <div class="mx-auto max-w-3xl px-6 py-14">
        <a href="{{ route('auctions.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-warm-600 hover:text-warm-800">
            <x-heroicon-o-arrow-left class="h-4 w-4 rtl:rotate-180" aria-hidden="true" />{{ __('auctions.site.title') }}
        </a>
        <p class="section-eyebrow mt-6">{{ __('auctions.site.eyebrow') }}</p>
        <h1 class="mt-2 font-display text-3xl font-semibold text-warm-900">{{ __('auctions.sell.title') }}</h1>
        <p class="mt-2 text-warm-700">{{ __('auctions.sell.intro') }}</p>

        <ol class="mt-6 grid gap-3 sm:grid-cols-3">
            @foreach(['send' => 'heroicon-o-paper-airplane', 'review' => 'heroicon-o-clipboard-document-check', 'sale' => 'heroicon-o-megaphone'] as $step => $icon)
                <li class="card-warm flex items-start gap-3 p-4 text-sm">
                    <x-dynamic-component :component="$icon" class="h-5 w-5 shrink-0 text-warm-600" aria-hidden="true" />
                    <span class="text-warm-800">{{ __('auctions.sell.steps.'.$step) }}</span>
                </li>
            @endforeach
        </ol>

        @if($errors->any())
            <div class="mt-6 flex gap-3 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">
                <x-heroicon-o-exclamation-triangle class="h-5 w-5 shrink-0" aria-hidden="true" /><p>{{ __('auctions.sell.check_fields') }}</p>
            </div>
        @endif

        <form method="POST" action="{{ route('auctions.sell.store') }}" enctype="multipart/form-data" class="card-warm mt-6 space-y-8 p-6">
            @csrf

            <fieldset class="grid gap-4 sm:grid-cols-2">
                <legend class="mb-2 flex items-center gap-2 font-display text-lg font-semibold text-warm-900"><x-heroicon-o-information-circle class="h-5 w-5 text-warm-600" aria-hidden="true" />{{ __('auctions.sell.sections.horse') }}</legend>
                <div>
                    <label for="horse_name" class="{{ $label }}">{{ $field('horse_name') }}</label>
                    <input id="horse_name" name="horse_name" value="{{ old('horse_name') }}" required maxlength="255" placeholder="{{ $placeholder('horse_name') }}" class="{{ $input }}">
                    @error('horse_name')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="gender" class="{{ $label }}">{{ $field('gender') }}</label>
                    <select id="gender" name="gender" required class="{{ $input }}">
                        <option value="">{{ $placeholder('gender') }}</option>
                        @foreach(\App\Models\AuctionLot::genderOptions() as $value => $text)
                            <option value="{{ $value }}" @selected(old('gender') === $value)>{{ $text }}</option>
                        @endforeach
                    </select>
                    @error('gender')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="born_on" class="{{ $label }}">{{ $field('born_on') }}</label>
                    <input id="born_on" name="born_on" type="date" value="{{ old('born_on') }}" max="{{ now()->subDay()->toDateString() }}" placeholder="{{ $placeholder('born_on') }}" class="{{ $input }}">
                    @error('born_on')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="colour" class="{{ $label }}">{{ $field('colour') }}</label>
                    <select id="colour" name="colour" class="{{ $input }}">
                        <option value="">{{ $placeholder('colour') }}</option>
                        @foreach(\App\Models\Stallion::colourOptions() as $value => $text)
                            <option value="{{ $value }}" @selected(old('colour') === $value)>{{ $text }}</option>
                        @endforeach
                    </select>
                </div>
                @foreach(['breed', 'height_cm', 'sire', 'dam', 'ueln', 'microchip'] as $name)
                    <div>
                        <label for="{{ $name }}" class="{{ $label }}">{{ $field($name) }}</label>
                        <input id="{{ $name }}" name="{{ $name }}" value="{{ old($name) }}" placeholder="{{ $placeholder($name) }}" class="{{ $input }}"
                               @if($name === 'height_cm') type="number" min="50" max="220" inputmode="numeric" @else maxlength="255" @endif
                               @if(in_array($name, ['ueln', 'microchip', 'height_cm'], true)) dir="ltr" @endif>
                        @error($name)<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                    </div>
                @endforeach
            </fieldset>

            <fieldset class="grid gap-4 sm:grid-cols-2">
                <legend class="mb-2 flex items-center gap-2 font-display text-lg font-semibold text-warm-900"><x-heroicon-o-users class="h-5 w-5 text-warm-600" aria-hidden="true" />{{ __('auctions.sell.sections.owner') }}</legend>
                @foreach(['owner_name', 'owner_town', 'breeder_name'] as $name)
                    <div>
                        <label for="{{ $name }}" class="{{ $label }}">{{ $field($name) }}</label>
                        <input id="{{ $name }}" name="{{ $name }}" value="{{ old($name, $name === 'owner_name' ? $customer->name : null) }}" @if($name === 'owner_name') required @endif maxlength="255" placeholder="{{ $placeholder($name) }}" class="{{ $input }}">
                        @error($name)<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                    </div>
                @endforeach
                <div>
                    <label for="asking_reserve" class="{{ $label }}">{{ $field('asking_reserve') }}</label>
                    <input id="asking_reserve" name="asking_reserve" type="number" min="0" step="1" inputmode="decimal" dir="ltr" value="{{ old('asking_reserve') }}" placeholder="{{ $placeholder('asking_reserve') }}" class="{{ $input }}">
                    <p class="mt-1 text-xs text-warm-700">{{ __('auctions.sell.reserve_help') }}</p>
                    @error('asking_reserve')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                </div>
            </fieldset>

            <fieldset class="space-y-4">
                <legend class="mb-2 flex items-center gap-2 font-display text-lg font-semibold text-warm-900"><x-heroicon-o-photo class="h-5 w-5 text-warm-600" aria-hidden="true" />{{ __('auctions.sell.sections.media') }}</legend>
                <div>
                    <label for="photos" class="{{ $label }}">{{ $field('photos') }}</label>
                    <input id="photos" name="photos[]" type="file" multiple required accept="image/jpeg,image/png,image/webp" class="{{ $input }}">
                    <p class="mt-1 text-xs text-warm-700">{{ __('auctions.sell.photos_help') }}</p>
                    @error('photos')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                    @error('photos.*')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="video-0" class="{{ $label }}">{{ $field('videos') }}</label>
                    @foreach([0, 1] as $i)
                        <input id="video-{{ $i }}" name="videos[]" type="url" value="{{ old('videos.'.$i) }}" dir="ltr" placeholder="https://www.youtube.com/watch?v=…" class="{{ $input }}">
                    @endforeach
                    @error('videos.*')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="documents" class="{{ $label }}">{{ $field('documents') }}</label>
                    <input id="documents" name="documents[]" type="file" multiple accept="application/pdf,image/jpeg,image/png,image/webp" class="{{ $input }}">
                    <p class="mt-1 text-xs text-warm-700">{{ __('auctions.sell.documents_help') }}</p>
                    @error('documents.*')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="notes" class="{{ $label }}">{{ $field('notes') }}</label>
                    <textarea id="notes" name="notes" rows="4" maxlength="3000" placeholder="{{ $placeholder('notes') }}" class="{{ $input }}">{{ old('notes') }}</textarea>
                </div>
            </fieldset>

            <label class="flex items-start gap-3 text-sm text-warm-900">
                <input type="checkbox" name="declare" value="1" required class="mt-0.5 h-5 w-5 rounded border-warm-300 text-warm-700 focus:ring-warm-500">
                <span>{{ __('auctions.sell.declare') }}</span>
            </label>
            @error('declare')<p class="text-sm font-semibold text-rose-700">{{ $message }}</p>@enderror

            <button type="submit" class="btn-warm w-full justify-center sm:w-auto"><x-heroicon-o-paper-airplane class="h-5 w-5" aria-hidden="true" />{{ __('auctions.sell.submit') }}</button>
        </form>
    </div>
</x-layouts.site>

@php
    $insights = $this->insights();
    $total = $insights->total();
    $previous = $insights->previous()?->total();
    $last = $insights->lastSubmittedAt();
    $results = $insights->fieldResults();
    $number = fn (int|float $n): string => rtrim(rtrim(number_format($n, 2, '.', ','), '0'), '.');
    $delta = $previous === null ? null : $total - $previous;
    $icons = [
        'choices' => 'heroicon-o-list-bullet',
        'multi' => 'heroicon-o-check-circle',
        'boolean' => 'heroicon-o-check',
        'nationality' => 'heroicon-o-globe-alt',
        'number' => 'heroicon-o-hashtag',
        'date' => 'heroicon-o-calendar',
    ];
    $money = $this->showsMoney() ? $insights->income()->totals() : null;
    $m = fn (int $baisa) => \App\Support\Money::formatHtml($baisa);
@endphp

<x-filament-panels::page>
    @vite('resources/js/form-charts.js')

    <div class="flex flex-col gap-y-6">
        {{ $this->form }}

        <p class="flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400">
            <x-heroicon-o-calendar-days class="h-4 w-4" aria-hidden="true" />
            <span dir="ltr">{{ $insights->start()->toDateString() }} &rarr; {{ $insights->to->toDateString() }}</span>
        </p>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <x-filament::section>
                <p class="flex items-center gap-1.5 text-sm font-medium text-gray-500 dark:text-gray-400">
                    <x-heroicon-o-inbox-arrow-down class="h-4 w-4" aria-hidden="true" />
                    {{ __('admin_form_insights.cards.submissions') }}
                </p>
                <p class="mt-1 text-3xl font-semibold text-gray-950 dark:text-white">{{ number_format($total) }}</p>
                @if($delta !== null)
                    <p @class([
                        'mt-1 flex items-center gap-1 text-xs',
                        'text-emerald-700 dark:text-emerald-400' => $delta > 0,
                        'text-red-700 dark:text-red-400' => $delta < 0,
                        'text-gray-500 dark:text-gray-400' => $delta === 0,
                    ])>
                        @if($delta > 0)
                            <x-heroicon-m-arrow-trending-up class="h-4 w-4" aria-hidden="true" />
                        @elseif($delta < 0)
                            <x-heroicon-m-arrow-trending-down class="h-4 w-4" aria-hidden="true" />
                        @endif
                        {{ __('admin_form_insights.cards.vs_previous', ['delta' => ($delta > 0 ? '+' : '').number_format($delta), 'previous' => number_format($previous)]) }}
                    </p>
                @else
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('admin_form_insights.cards.all_time') }}</p>
                @endif
            </x-filament::section>

            <x-filament::section>
                <p class="flex items-center gap-1.5 text-sm font-medium text-gray-500 dark:text-gray-400">
                    <x-heroicon-o-envelope class="h-4 w-4" aria-hidden="true" />
                    {{ __('admin_form_insights.cards.unread') }}
                </p>
                <p class="mt-1 text-3xl font-semibold text-gray-950 dark:text-white">{{ number_format($insights->unread()) }}</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('admin_form_insights.cards.unread_hint') }}</p>
            </x-filament::section>

            <x-filament::section>
                <p class="flex items-center gap-1.5 text-sm font-medium text-gray-500 dark:text-gray-400">
                    <x-heroicon-o-clock class="h-4 w-4" aria-hidden="true" />
                    {{ __('admin_form_insights.cards.last_submission') }}
                </p>
                <p class="mt-1 text-3xl font-semibold text-gray-950 dark:text-white">{{ $last ? $last->locale(app()->getLocale())->diffForHumans() : __('admin_form_insights.cards.never') }}</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400" dir="ltr">{{ $last?->format('Y-m-d H:i') }}</p>
            </x-filament::section>
        </div>

        @if($total === 0)
            <x-filament::section>
                <div class="flex flex-col items-center gap-2 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                    <x-heroicon-o-chart-bar class="h-8 w-8 text-gray-400" aria-hidden="true" />
                    {{ __('admin_form_insights.empty') }}
                </div>
            </x-filament::section>
        @else
            <x-filament::section :heading="__('admin_form_insights.sections.over_time')" :description="__('admin_form_insights.sections.over_time_by_'.$insights->bucket())" icon="heroicon-o-chart-bar">
                <x-form-insights.chart id="timeline" :spec="\App\Services\Forms\FormChart::timeline($insights->timeline(), __('admin_form_insights.sections.over_time'))" />
            </x-filament::section>
        @endif

        @if($insights->isPaidForm())
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <x-filament::section :heading="__('admin_form_insights.sections.orders_by_status')" :description="__('admin_form_insights.sections.orders_by_status_hint')" icon="heroicon-o-clipboard-document-list">
                    <x-form-insights.chart id="orders" :spec="\App\Services\Forms\FormChart::counts($insights->ordersByStatus(), __('admin_form_insights.sections.orders_by_status'))" />
                </x-filament::section>

                @if($money !== null)
                    <x-filament::section :heading="__('admin_form_insights.sections.money')" :description="__('admin_form_insights.sections.money_hint')" icon="heroicon-o-banknotes">
                        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            @foreach([
                                ['heroicon-o-credit-card', __('admin_income_report.cards.collected'), $money['collected'], __('admin_income_report.cards.collected_hint', ['count' => $money['orders']])],
                                ['heroicon-o-building-library', __('admin_income_report.cards.due_to_us'), $money['due_to_us'], __('admin_form_insights.money.due_hint', ['fee' => \App\Support\Money::format($money['fee'] + $money['vat_on_fee']), 'commission' => \App\Support\Money::format(\App\Services\Reports\IncomeStatement::commissionWithVat($money))])],
                                ['heroicon-o-briefcase', __('admin_income_report.cards.client_keeps'), $money['client_keeps'], __('admin_income_report.cards.client_hint')],
                                ['heroicon-o-arrow-uturn-left', __('admin_income_report.cards.refunded'), $money['refunded'], __('admin_income_report.cards.refunded_hint')],
                            ] as [$icon, $label, $value, $hint])
                                <div>
                                    <dt class="flex items-center gap-1.5 text-sm font-medium text-gray-500 dark:text-gray-400">
                                        <x-dynamic-component :component="$icon" class="h-4 w-4" aria-hidden="true" />
                                        {{ $label }}
                                    </dt>
                                    <dd class="mt-1 text-xl font-semibold text-gray-950 dark:text-white" dir="ltr">{{ $m($value) }}</dd>
                                    <dd class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $hint }}</dd>
                                </div>
                            @endforeach
                        </dl>
                        <x-filament::link :href="\App\Filament\Pages\IncomeReport::getUrl()" icon="heroicon-m-arrow-top-right-on-square" class="mt-4" size="sm">
                            {{ __('admin_form_insights.money.open_report') }}
                        </x-filament::link>
                    </x-filament::section>
                @endif
            </div>
        @endif

        @if($total > 0)
            @if($results->isEmpty())
                <x-filament::section>
                    <div class="flex flex-col items-center gap-2 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                        <x-heroicon-o-information-circle class="h-8 w-8 text-gray-400" aria-hidden="true" />
                        {{ __('admin_form_insights.no_chartable') }}
                    </div>
                </x-filament::section>
            @else
                <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                    @foreach($results as $key => $result)
                        <x-filament::section
                            :heading="$result['label']"
                            :description="__('admin_form_insights.field.answered', ['answered' => number_format($result['answered']), 'total' => number_format($result['total']), 'type' => __('admin_form_insights.kinds.'.$result['kind'])])"
                            :icon="$icons[$result['kind']]"
                            @class(['xl:col-span-2' => $result['kind'] === 'nationality'])
                        >
                            @if($result['answered'] === 0)
                                <p class="py-4 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('admin_form_insights.field.no_answers') }}</p>
                            @else
                                @if($result['kind'] === 'number' && $result['stats'])
                                    <dl class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                                        @foreach(['min', 'mean', 'median', 'max'] as $stat)
                                            <div class="rounded-lg bg-gray-50 px-3 py-2 dark:bg-white/5">
                                                <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('admin_form_insights.stats.'.$stat) }}</dt>
                                                <dd class="text-lg font-semibold text-gray-950 dark:text-white" dir="ltr">{{ $number($result['stats'][$stat]) }}</dd>
                                            </div>
                                        @endforeach
                                    </dl>
                                @endif

                                @if($result['kind'] === 'nationality')
                                    <div class="grid grid-cols-1 gap-6 lg:grid-cols-5">
                                        <div class="lg:col-span-3">
                                            <x-form-insights.chart :id="$key.'-map'" :spec="\App\Services\Forms\FormChart::forField($result, map: true)" :table="false" />
                                        </div>
                                        <div class="lg:col-span-2">
                                            <x-form-insights.chart :id="$key" :spec="\App\Services\Forms\FormChart::forField($result)" />
                                        </div>
                                    </div>
                                @else
                                    <x-form-insights.chart :id="$key" :spec="\App\Services\Forms\FormChart::forField($result)" />
                                @endif
                            @endif
                        </x-filament::section>
                    @endforeach
                </div>
            @endif
        @endif
    </div>
</x-filament-panels::page>

@php
    $statement = $this->statement();
    $t = $statement->totals();
    $m = fn (int $baisa) => \App\Support\Money::formatHtml($baisa);
    $cards = [
        ['label' => __('admin_income_report.cards.collected'), 'value' => $t['collected'], 'hint' => __('admin_income_report.cards.collected_hint', ['count' => $t['orders']])],
        ['label' => __('admin_income_report.cards.due_to_us'), 'value' => $t['due_to_us'], 'hint' => new \Illuminate\Support\HtmlString(__('admin_income_report.cards.due_hint', ['fee' => $m($t['fee']), 'vat' => $m($t['vat_on_fee']), 'commission' => $m(\App\Services\Reports\IncomeStatement::commissionWithVat($t))]))],
        ['label' => __('admin_income_report.cards.client_keeps'), 'value' => $t['client_keeps'], 'hint' => __('admin_income_report.cards.client_hint')],
        ['label' => __('admin_income_report.cards.refunded'), 'value' => $t['refunded'], 'hint' => __('admin_income_report.cards.refunded_hint')],
    ];
    $services = $statement->byService();
    $days = $statement->byDay()->filter(fn (array $day) => $day['orders'] > 0);
    $gateways = $statement->byGateway();
    $withCommissionVat = $t['vat_on_commission'] > 0;
@endphp

<x-filament-panels::page>
    <div class="flex flex-col gap-y-6">
        {{ $this->form }}

        <p class="text-sm text-gray-500 dark:text-gray-400" dir="ltr">{{ $statement->from->toDateString() }} &rarr; {{ $statement->to->toDateString() }}</p>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach($cards as $card)
                <x-filament::section>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $card['label'] }}</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white" dir="ltr">{{ $m($card['value']) }}</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $card['hint'] }}</p>
                </x-filament::section>
            @endforeach
        </div>

        @if($t['orders'] === 0)
            <x-filament::section>
                <p class="text-center text-sm text-gray-500 dark:text-gray-400">{{ __('admin_income_report.empty') }}</p>
            </x-filament::section>
        @else
            <x-filament::section :heading="__('admin_income_report.sections.breakdown')">
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @foreach([
                            [__('statement.collected'), $t['collected'], false],
                            [__('statement.service_price'), $t['price'], false],
                            [__('statement.vat_on_price'), $t['vat_on_price'], false],
                            [__('statement.service_fees'), $t['fee'], false],
                            [__('statement.vat_on_fees'), $t['vat_on_fee'], false],
                            [__('statement.commission'), $t['commission'], false],
                            ...($t['vat_on_commission'] > 0 ? [[__('statement.vat_on_commission'), $t['vat_on_commission'], false]] : []),
                            [__('statement.refunded'), $t['refunded'], false],
                            [__('statement.due_to_us'), $t['due_to_us'], true],
                            [__('statement.client_keeps'), $t['client_keeps'], true],
                        ] as [$label, $value, $strong])
                            <tr @class(['font-semibold' => $strong])>
                                <td class="py-2">{{ $label }}</td>
                                <td class="py-2 text-end" dir="ltr">{{ $m($value) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-filament::section>

            <x-filament::section :heading="__('statement.by_service')">
                @include('filament.pages.income-report-table', ['groups' => $services->map(fn ($g) => ['label' => $g['label']] + $g['totals']), 'first' => __('statement.service'), 'm' => $m, 'withCommissionVat' => $withCommissionVat])
            </x-filament::section>

            <x-filament::section :heading="__('statement.by_day')">
                @include('filament.pages.income-report-table', ['groups' => $days->map(fn ($g, $date) => ['label' => $date] + $g)->values(), 'first' => __('statement.date'), 'm' => $m, 'ltrLabel' => true, 'withCommissionVat' => $withCommissionVat])
            </x-filament::section>

            <x-filament::section :heading="__('admin_income_report.sections.by_gateway')">
                @include('filament.pages.income-report-table', ['groups' => $gateways->map(fn ($g) => ['label' => $g['label']] + $g['totals']), 'first' => __('statement.gateway'), 'm' => $m, 'withCommissionVat' => $withCommissionVat])
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>

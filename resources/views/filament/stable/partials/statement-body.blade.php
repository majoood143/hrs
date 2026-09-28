{{-- A stable's statement on screen (owner panel and admin page). Needs $statement. --}}
@php
    $t = $statement->totals();
    $opening = $statement->opening();
    $closing = $statement->closing();
    $m = fn (int $baisa) => \App\Support\Money::formatHtml($baisa);
    $rows = $statement->rows();
    $settlements = $statement->settlements();
    $unmarked = \App\Services\Reports\StableStatement::unmarkedAtStable($statement->stable);
    $cards = [
        ['label' => __('stable_statement.cards.bookings'), 'value' => (string) $t['bookings'], 'hint' => trans_choice('stable_bookings.riders_count', $t['riders'], ['count' => $t['riders']]), 'icon' => 'heroicon-o-ticket'],
        ['label' => __('stable_statement.cards.collected'), 'value' => $m($t['collected']), 'hint' => __('stable_statement.cards.collected_hint', ['ours' => $m($t['collected_by_us']), 'yours' => $m($t['collected_by_stable'])]), 'icon' => 'heroicon-o-banknotes'],
        ['label' => __('stable_statement.cards.stable_share'), 'value' => $m($t['stable_share']), 'hint' => __('stable_statement.cards.stable_share_hint'), 'icon' => 'heroicon-o-home-modern'],
        ['label' => __('stable_statement.cards.commission'), 'value' => $m($t['commission'] + $t['vat_on_commission']), 'hint' => __('stable_statement.cards.commission_hint', ['fee' => $m($t['fee'])]), 'icon' => 'heroicon-o-receipt-percent'],
    ];
@endphp

<p class="text-sm text-gray-500 dark:text-gray-400" dir="ltr">{{ $statement->from->toDateString() }} &rarr; {{ $statement->to->toDateString() }}</p>

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
    @foreach($cards as $card)
        <x-filament::section>
            <p class="flex items-center gap-1.5 text-sm font-medium text-gray-500 dark:text-gray-400">
                <x-filament::icon :icon="$card['icon']" class="h-4 w-4" aria-hidden="true" />{{ $card['label'] }}
            </p>
            <p class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white" dir="ltr">{{ $card['value'] }}</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $card['hint'] }}</p>
        </x-filament::section>
    @endforeach
</div>

<x-filament::section :heading="__('stable_statement.sections.balance')" icon="heroicon-o-scale">
    <table class="w-full text-sm">
        <tbody class="divide-y divide-gray-200 dark:divide-white/10">
            @foreach([
                [__('stable_statement.summary.opening'), $opening, false],
                [__('stable_statement.summary.owed_to_stable'), $t['owed_to_stable'], false],
                [__('stable_statement.summary.owed_by_stable'), -$t['owed_by_stable'], false],
                [__('stable_statement.summary.payouts'), -$t['payouts'], false],
                [__('stable_statement.summary.receipts'), $t['receipts'], false],
                [__('stable_statement.summary.closing'), $closing, true],
            ] as [$label, $value, $strong])
                <tr @class(['font-semibold' => $strong])>
                    <td class="py-2">{{ $label }}</td>
                    <td class="py-2 text-end" dir="ltr">{{ $m($value) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p @class([
        'mt-4 flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold',
        'bg-emerald-50 text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-300' => $closing > 0,
        'bg-amber-50 text-amber-800 dark:bg-amber-500/10 dark:text-amber-300' => $closing < 0,
        'bg-gray-50 text-gray-700 dark:bg-white/5 dark:text-gray-300' => $closing === 0,
    ])>
        <x-filament::icon icon="heroicon-o-scale" class="h-5 w-5" aria-hidden="true" />
        {{ app(\App\Services\Reports\StableStatementExport::class)->balanceSentence($closing) }}
    </p>
    @if($unmarked > 0)
        <p class="mt-3 flex items-center gap-2 text-sm text-amber-700 dark:text-amber-300">
            <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-5 w-5" aria-hidden="true" />
            {{ trans_choice('stable_statement.unmarked', $unmarked, ['count' => $unmarked]) }}
        </p>
    @endif
</x-filament::section>

<x-filament::section :heading="__('stable_statement.sections.bookings')" icon="heroicon-o-ticket">
    @if($rows->isEmpty())
        <p class="text-center text-sm text-gray-500 dark:text-gray-400">{{ __('stable_statement.empty') }}</p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-start text-xs uppercase text-gray-500 dark:border-white/10 dark:text-gray-400">
                        <th class="py-2 pe-3 text-start">{{ __('statement.date') }}</th>
                        <th class="py-2 pe-3 text-start">{{ __('stable_statement.fields.booking') }}</th>
                        <th class="py-2 pe-3 text-start">{{ __('stable_statement.fields.session') }}</th>
                        <th class="py-2 pe-3 text-start">{{ __('stable_statement.fields.paid_how') }}</th>
                        <th class="py-2 pe-3 text-end">{{ __('stable_statement.fields.collected') }}</th>
                        <th class="py-2 pe-3 text-end">{{ __('stable_statement.fields.commission') }}</th>
                        <th class="py-2 pe-3 text-end">{{ __('stable_statement.fields.stable_share') }}</th>
                        <th class="py-2 text-end">{{ __('stable_statement.fields.net') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach($rows as $row)
                        <tr>
                            <td class="py-2 pe-3" dir="ltr">{{ $row->date->format('Y-m-d') }}</td>
                            <td class="py-2 pe-3 font-mono text-xs" dir="ltr">{{ $row->bookingReference ?? $row->orderNumber }}</td>
                            <td class="py-2 pe-3">{{ $row->service }} <span class="text-xs text-gray-500">· {{ trans_choice('stable_bookings.riders_count', $row->riders, ['count' => $row->riders]) }}</span></td>
                            <td class="py-2 pe-3">
                                <x-filament::badge :color="$row->heldByUs() ? 'info' : 'gray'" size="sm">{{ __('stable_statement.kinds.'.$row->kind) }}</x-filament::badge>
                            </td>
                            <td class="py-2 pe-3 text-end" dir="ltr">{{ $m($row->collected()) }}@if($row->refunded > 0)<span class="block text-xs text-red-600">−{{ $m($row->refunded) }}</span>@endif</td>
                            <td class="py-2 pe-3 text-end" dir="ltr">{{ $m($row->commission + $row->vatOnCommission) }}</td>
                            <td class="py-2 pe-3 text-end" dir="ltr">{{ $m($row->stableShare()) }}</td>
                            <td @class(['py-2 text-end font-semibold', 'text-emerald-700 dark:text-emerald-400' => $row->net() > 0, 'text-amber-700 dark:text-amber-400' => $row->net() < 0]) dir="ltr">{{ $m($row->net()) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-filament::section>

<x-filament::section :heading="__('stable_statement.sections.settlements')" icon="heroicon-o-arrows-right-left">
    @if($settlements->isEmpty())
        <p class="text-center text-sm text-gray-500 dark:text-gray-400">{{ __('stable_statement.no_settlements') }}</p>
    @else
        <table class="w-full text-sm">
            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                @foreach($settlements as $settlement)
                    <tr>
                        <td class="py-2 pe-3" dir="ltr">{{ $settlement->paid_on->toDateString() }}</td>
                        <td class="py-2 pe-3">
                            <x-filament::badge :color="$settlement->direction === 'payout' ? 'success' : 'info'" :icon="$settlement->direction === 'payout' ? 'heroicon-o-arrow-up-right' : 'heroicon-o-arrow-down-left'" size="sm">
                                {{ __('stable_statement.directions.'.$settlement->direction) }}
                            </x-filament::badge>
                        </td>
                        <td class="py-2 pe-3">{{ __('stable_statement.methods.'.$settlement->method) }}@if($settlement->reference) · <span dir="ltr">{{ $settlement->reference }}</span>@endif</td>
                        <td class="py-2 text-end font-semibold" dir="ltr">{{ $m($settlement->amountBaisa()) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</x-filament::section>

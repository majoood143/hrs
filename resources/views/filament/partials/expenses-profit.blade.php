{{-- Income less expenses for a period (admin Income report, stable Statement). Needs $income (baisa), $incomeLabel, $report (ExpenseReport), $netLabel. --}}
@php
    $m = fn (int $baisa) => \App\Support\Money::formatHtml($baisa);
    $spent = $report->total();
    $net = $income - $spent;
@endphp

<x-filament::section :heading="__('expenses.sections.profit')" icon="heroicon-o-calculator">
    <table class="w-full text-sm">
        <tbody class="divide-y divide-gray-200 dark:divide-white/10">
            <tr>
                <td class="py-2">{{ $incomeLabel }}</td>
                <td class="py-2 text-end" dir="ltr">{{ $m($income) }}</td>
            </tr>
            @forelse($report->byCategory() as $category)
                <tr class="text-gray-600 dark:text-gray-300">
                    <td class="py-2 ps-4">{{ $category['label'] }} <span class="text-xs text-gray-400">({{ $category['count'] }})</span></td>
                    <td class="py-2 text-end" dir="ltr">{{ $m(-$category['total']) }}</td>
                </tr>
            @empty
                <tr class="text-gray-500 dark:text-gray-400">
                    <td class="py-2 ps-4">{{ __('expenses.none_in_period') }}</td>
                    <td class="py-2 text-end" dir="ltr">{{ $m(0) }}</td>
                </tr>
            @endforelse
            <tr>
                <td class="py-2">{{ __('expenses.total') }}</td>
                <td class="py-2 text-end" dir="ltr">{{ $m(-$spent) }}</td>
            </tr>
            <tr @class(['font-semibold', 'text-emerald-700 dark:text-emerald-300' => $net > 0, 'text-danger-600 dark:text-danger-400' => $net < 0])>
                <td class="py-2">{{ $netLabel }}</td>
                <td class="py-2 text-end" dir="ltr">{{ $m($net) }}</td>
            </tr>
        </tbody>
    </table>
</x-filament::section>

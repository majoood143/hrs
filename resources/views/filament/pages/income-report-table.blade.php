<div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-start text-gray-500 dark:text-gray-400">
                <th class="py-2 pe-4 text-start font-medium">{{ $first }}</th>
                <th class="py-2 pe-4 text-end font-medium">{{ __('statement.orders') }}</th>
                <th class="py-2 pe-4 text-end font-medium">{{ __('statement.collected') }}</th>
                <th class="py-2 pe-4 text-end font-medium">{{ __('statement.fee_and_vat') }}</th>
                <th class="py-2 pe-4 text-end font-medium">{{ ($withCommissionVat ?? false) ? __('statement.commission_and_vat') : __('statement.commission') }}</th>
                <th class="py-2 text-end font-medium">{{ __('statement.due_to_us') }}</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-white/10">
            @foreach($groups as $group)
                <tr>
                    <td class="py-2 pe-4" @if($ltrLabel ?? false) dir="ltr" @endif>{{ $group['label'] }}</td>
                    <td class="py-2 pe-4 text-end">{{ $group['orders'] }}</td>
                    <td class="py-2 pe-4 text-end" dir="ltr">{{ $m($group['collected']) }}</td>
                    <td class="py-2 pe-4 text-end" dir="ltr">{{ $m($group['fee'] + $group['vat_on_fee']) }}</td>
                    <td class="py-2 pe-4 text-end" dir="ltr">{{ $m(\App\Services\Reports\IncomeStatement::commissionWithVat($group)) }}</td>
                    <td class="py-2 text-end font-semibold" dir="ltr">{{ $m($group['due_to_us']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

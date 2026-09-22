<?php

namespace App\Services\Reports;

use App\Enums\PaymentGateway;
use App\Models\Service;
use App\Models\SiteSetting;
use App\Support\Locale;
use App\Support\Money;

/** The income statement as a CSV a spreadsheet opens (UTF-8 with a byte-order mark, so Arabic survives). */
class StatementCsv
{
    /** @param  resource  $out */
    public function write($out, IncomeStatement $statement, string $locale): void
    {
        Locale::within($locale, function () use ($out, $statement) {
            $t = $statement->totals();
            $plain = fn (int $baisa) => Money::plain($baisa);

            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [__('statement.title')]);
            fputcsv($out, [__('statement.period'), $statement->from->toDateString().' → '.$statement->to->toDateString()]);
            fputcsv($out, [__('statement.currency'), SiteSetting::currency()['code']]);

            if ($statement->serviceId) {
                fputcsv($out, [__('statement.service'), Service::find($statement->serviceId)?->localizedName() ?? '#'.$statement->serviceId]);
            }

            if ($statement->gateway) {
                fputcsv($out, [__('statement.gateway'), PaymentGateway::tryFrom($statement->gateway)?->label() ?? $statement->gateway]);
            }

            fputcsv($out, []);
            fputcsv($out, [__('statement.summary')]);

            foreach ($this->summaryLines($t) as [$label, $baisa]) {
                fputcsv($out, [$label, $baisa === null ? '' : $plain($baisa)]);
            }

            fputcsv($out, []);
            fputcsv($out, [__('statement.by_service')]);
            $commissionLabel = $t['vat_on_commission'] > 0 ? __('statement.commission_and_vat') : __('statement.commission');
            fputcsv($out, [__('statement.service'), __('statement.orders'), __('statement.collected'), __('statement.fee_and_vat'), $commissionLabel, __('statement.refunded'), __('statement.due_to_us')]);

            foreach ($statement->byService() as $group) {
                $g = $group['totals'];
                fputcsv($out, [$group['label'], $g['orders'], $plain($g['collected']), $plain($g['fee'] + $g['vat_on_fee']), $plain(IncomeStatement::commissionWithVat($g)), $plain($g['refunded']), $plain($g['due_to_us'])]);
            }

            fputcsv($out, []);
            fputcsv($out, [__('statement.by_day')]);
            fputcsv($out, [__('statement.date'), __('statement.orders'), __('statement.collected'), __('statement.fee_and_vat'), $commissionLabel, __('statement.refunded'), __('statement.due_to_us')]);

            foreach ($statement->byDay()->filter(fn (array $day) => $day['orders'] > 0) as $date => $g) {
                fputcsv($out, [$date, $g['orders'], $plain($g['collected']), $plain($g['fee'] + $g['vat_on_fee']), $plain(IncomeStatement::commissionWithVat($g)), $plain($g['refunded']), $plain($g['due_to_us'])]);
            }

            fputcsv($out, []);
            fputcsv($out, [__('statement.orders_list')]);
            fputcsv($out, [__('statement.date'), __('statement.order'), __('statement.collected'), __('statement.fee'), __('statement.vat_on_fees'), $commissionLabel, __('statement.refunded'), __('statement.due_to_us')]);

            foreach ($statement->rows() as $row) {
                fputcsv($out, [$row->paidAt->format('Y-m-d H:i'), $row->orderNumber, $plain($row->total), $plain($row->fee), $plain($row->vatOnFee), $plain($row->commission + $row->vatOnCommission), $plain($row->refunded), $plain($row->dueToUs())]);
            }
        });
    }

    /**
     * The summary block, in the order it is read: what came in, what is ours, what stays with the client.
     *
     * @param  array<string, int>  $t
     * @return list<array{0: string, 1: ?int}>
     */
    public function summaryLines(array $t): array
    {
        return [
            [__('statement.orders_paid'), null],
            [__('statement.collected'), $t['collected']],
            [__('statement.service_price'), $t['price']],
            [__('statement.vat_on_price'), $t['vat_on_price']],
            [__('statement.service_fees'), $t['fee']],
            [__('statement.vat_on_fees'), $t['vat_on_fee']],
            [__('statement.refunded'), $t['refunded']],
            [__('statement.commission'), $t['commission']],
            ...($t['vat_on_commission'] > 0 ? [[__('statement.vat_on_commission'), $t['vat_on_commission']]] : []),
            [__('statement.due_to_us'), $t['due_to_us']],
            [__('statement.client_keeps'), $t['client_keeps']],
        ];
    }
}

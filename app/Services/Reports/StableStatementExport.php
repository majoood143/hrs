<?php

namespace App\Services\Reports;

use App\Models\SiteSetting;
use App\Models\StableSettlement;
use App\Services\Pdf\MpdfFactory;
use App\Services\Racing\RacingPdf;
use App\Support\Locale;
use App\Support\Money;
use App\Support\PdfText;
use Mpdf\Output\Destination;

/**
 * A stable's statement as a PDF (to send to the stable, or for the stable to keep) and as a CSV,
 * in English or Arabic: the balance brought forward, the bookings, the payouts and receipts, and
 * the balance carried forward.
 */
class StableStatementExport
{
    public function __construct(private readonly MpdfFactory $factory) {}

    public function filename(StableStatement $statement, string $extension): string
    {
        return 'statement-'.$statement->stable->slug.'-'.$statement->from->toDateString().'_'.$statement->to->toDateString().'.'.$extension;
    }

    /** @return list<array{0: string, 1: int, 2?: bool}> label, amount (baisa), strong */
    public function summary(StableStatement $statement): array
    {
        $t = $statement->totals();
        $opening = $statement->opening();
        $closing = $statement->closing();

        return [
            [__('stable_statement.summary.opening'), $opening],
            [__('stable_statement.summary.owed_to_stable'), $t['owed_to_stable']],
            [__('stable_statement.summary.owed_by_stable'), -$t['owed_by_stable']],
            [__('stable_statement.summary.payouts'), -$t['payouts']],
            [__('stable_statement.summary.receipts'), $t['receipts']],
            [__('stable_statement.summary.closing'), $closing, true],
        ];
    }

    public function balanceSentence(int $balance): string
    {
        return __('stable_statement.balance.'.($balance > 0 ? 'we_owe' : ($balance < 0 ? 'you_owe' : 'settled')), ['amount' => Money::format(abs($balance))]);
    }

    public function html(StableStatement $statement, string $locale): string
    {
        return Locale::within($locale, function () use ($statement, $locale) {
            $rtl = $locale === 'ar';

            return view('pdf.stable-statement', [
                'statement' => $statement,
                'stable' => $statement->stable,
                'totals' => $statement->totals(),
                'rows' => $statement->rows(),
                'settlements' => $statement->settlements(),
                'summary' => $this->summary($statement),
                'balanceSentence' => $this->balanceSentence($statement->closing()),
                'currency' => SiteSetting::currency()['code'],
                'currencyIcon' => app(RacingPdf::class)->currencyIcon(),
                'logo' => app(RacingPdf::class)->siteLogo(),
                'siteName' => SiteSetting::siteName(),
                'locale' => $locale,
                'rtl' => $rtl,
                'd' => fn (?string $text) => PdfText::dir($text, $rtl),
            ])->render();
        });
    }

    public function pdf(StableStatement $statement, string $locale): string
    {
        $mpdf = $this->factory->make([
            'format' => 'A4-L',
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 10,
            'margin_bottom' => 14,
            'margin_footer' => 5,
            'default_font_size' => 8.5,
        ]);

        $title = Locale::within($locale, fn () => __('stable_statement.title'));
        $mpdf->SetTitle($title.' — '.$statement->stable->en_name);
        $mpdf->SetAuthor(SiteSetting::siteName());
        $mpdf->SetDirectionality($locale === 'ar' ? 'rtl' : 'ltr');
        $mpdf->SetHTMLFooter(Locale::within($locale, fn () => '<div style="font-family: cairo; font-size: 7pt; color: #666666; text-align: center; border-top: 0.2mm solid #cccccc; padding-top: 1mm;">'.e(__('statement.page')).' {PAGENO} / {nbpg}</div>'));
        @ini_set('pcre.backtrack_limit', '5000000');

        $mpdf->WriteHTML($this->html($statement, $locale));

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    /** @param  resource  $out */
    public function csv($out, StableStatement $statement, string $locale): void
    {
        Locale::within($locale, function () use ($out, $statement) {
            $plain = fn (int $baisa) => Money::plain($baisa);

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [__('stable_statement.title'), $statement->stable->name]);
            fputcsv($out, [__('statement.period'), $statement->from->toDateString().' → '.$statement->to->toDateString()]);
            fputcsv($out, [__('statement.currency'), SiteSetting::currency()['code']]);
            fputcsv($out, []);

            foreach ($this->summary($statement) as [$label, $baisa]) {
                fputcsv($out, [$label, $plain($baisa)]);
            }

            fputcsv($out, []);
            fputcsv($out, [__('stable_statement.sections.bookings')]);
            fputcsv($out, [
                __('statement.date'), __('stable_statement.fields.booking'), __('statement.order'), __('stable_statement.fields.session'),
                __('stable_statement.fields.riders'), __('stable_statement.fields.paid_how'), __('stable_statement.fields.collected'),
                __('stable_statement.fields.refunded'), __('stable_statement.fields.stable_share'), __('stable_statement.fields.fee'),
                __('stable_statement.fields.commission'), __('stable_statement.fields.net'),
            ]);

            foreach ($statement->rows() as $row) {
                fputcsv($out, [
                    $row->date->toDateString(), $row->bookingReference, $row->orderNumber, $row->service, $row->riders,
                    __('stable_statement.kinds.'.$row->kind), $plain($row->collected()), $plain($row->refunded),
                    $plain($row->stableShare()), $plain($row->fee + $row->vatOnFee), $plain($row->commission + $row->vatOnCommission), $plain($row->net()),
                ]);
            }

            fputcsv($out, []);
            fputcsv($out, [__('stable_statement.sections.settlements')]);
            fputcsv($out, [__('stable_statement.fields.paid_on'), __('stable_statement.fields.direction'), __('stable_statement.fields.method'), __('stable_statement.fields.reference'), __('stable_statement.fields.amount')]);

            foreach ($statement->settlements() as $settlement) {
                /** @var StableSettlement $settlement */
                fputcsv($out, [
                    $settlement->paid_on->toDateString(), __('stable_statement.directions.'.$settlement->direction),
                    __('stable_statement.methods.'.$settlement->method), $settlement->reference, $plain($settlement->amountBaisa()),
                ]);
            }
        });
    }
}

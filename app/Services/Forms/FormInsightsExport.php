<?php

namespace App\Services\Forms;

use App\Enums\OrderStatus;
use App\Models\SiteSetting;
use App\Services\Pdf\MpdfFactory;
use App\Services\Racing\RacingPdf;
use App\Services\Reports\IncomeStatement;
use App\Support\Locale;
use App\Support\Money;
use App\Support\PdfText;
use Illuminate\Support\Str;
use Mpdf\Output\Destination;

/**
 * A form's insights as a download: a CSV (UTF-8 with a byte-order mark, so Arabic survives in a
 * spreadsheet) and an A4 PDF of plain tables (mPDF draws no charts: each answer gets a gray bar).
 * Both carry the same numbers as the Insights page, in the language picked there.
 */
class FormInsightsExport
{
    public function __construct(private readonly MpdfFactory $factory) {}

    public function filename(FormInsights $insights, string $extension): string
    {
        $slug = Str::slug((string) $insights->form->slug) ?: 'form-'.$insights->form->getKey();

        return 'form-insights-'.$slug.'-'.$insights->start()->toDateString().'_'.$insights->to->toDateString().'.'.$extension;
    }

    /**
     * Everything the page shows, as plain data in the current language.
     *
     * @return array<string, mixed>
     */
    public function data(FormInsights $insights, bool $withMoney): array
    {
        $previous = $insights->previous()?->total();

        return [
            'form' => (string) $insights->form->name,
            'period' => $insights->start()->toDateString().' → '.$insights->to->toDateString(),
            'status' => $insights->orderStatus ? OrderStatus::from($insights->orderStatus)->label() : null,
            'total' => $insights->total(),
            'previous' => $previous,
            'unread' => $insights->unread(),
            'last' => $insights->lastSubmittedAt()?->format('Y-m-d H:i'),
            'timeline' => $insights->timeline()->filter(fn (array $b) => $b['count'] > 0)->values()->all(),
            'fields' => $insights->fieldResults()->values()->all(),
            'orders' => $insights->isPaidForm() ? $insights->ordersByStatus() : null,
            'money' => $withMoney ? $insights->income()->totals() : null,
        ];
    }

    /** @param  resource  $out */
    public function csv($out, FormInsights $insights, string $locale, bool $withMoney): void
    {
        Locale::within($locale, function () use ($out, $insights, $withMoney) {
            $d = $this->data($insights, $withMoney);
            $share = fn (float $s): string => rtrim(rtrim(number_format($s, 1, '.', ''), '0'), '.').'%';

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [__('admin_form_insights.export.title'), $d['form']]);
            fputcsv($out, [__('admin_form_insights.export.period'), $d['period']]);

            if ($d['status']) {
                fputcsv($out, [__('admin_form_insights.filters.order_status'), $d['status']]);
            }

            fputcsv($out, [__('admin_form_insights.cards.submissions'), $d['total']]);

            if ($d['previous'] !== null) {
                fputcsv($out, [__('admin_form_insights.export.previous_period'), $d['previous']]);
            }

            fputcsv($out, [__('admin_form_insights.cards.unread'), $d['unread']]);
            fputcsv($out, [__('admin_form_insights.cards.last_submission'), $d['last'] ?? '']);

            if ($d['money']) {
                fputcsv($out, []);
                fputcsv($out, [__('admin_form_insights.sections.money'), SiteSetting::currency()['code']]);

                foreach ($this->moneyLines($d['money']) as [$label, $baisa]) {
                    fputcsv($out, [$label, Money::plain($baisa)]);
                }
            }

            if ($d['orders']) {
                fputcsv($out, []);
                fputcsv($out, [__('admin_form_insights.sections.orders_by_status'), __('form_charts.count')]);

                foreach ($d['orders'] as $row) {
                    fputcsv($out, [$row['label'], $row['count']]);
                }
            }

            fputcsv($out, []);
            fputcsv($out, [__('admin_form_insights.sections.over_time'), __('form_charts.count')]);

            foreach ($d['timeline'] as $bucket) {
                fputcsv($out, [$bucket['label'], $bucket['count']]);
            }

            foreach ($d['fields'] as $field) {
                fputcsv($out, []);
                fputcsv($out, [$field['label'], __('admin_form_insights.field.answered', ['answered' => $field['answered'], 'total' => $field['total'], 'type' => __('admin_form_insights.kinds.'.$field['kind'])])]);

                if (! empty($field['stats'])) {
                    foreach (['min', 'mean', 'median', 'max'] as $stat) {
                        fputcsv($out, [__('admin_form_insights.stats.'.$stat), $field['stats'][$stat]]);
                    }
                }

                fputcsv($out, [__('form_charts.answer'), __('form_charts.count'), __('form_charts.share')]);

                foreach ($field['rows'] as $row) {
                    fputcsv($out, [$row['label'], $row['count'], $share($row['share'])]);
                }
            }
        });
    }

    public function pdf(FormInsights $insights, string $locale, bool $withMoney): string
    {
        $mpdf = $this->factory->make([
            'margin_left' => 12,
            'margin_right' => 12,
            'margin_top' => 12,
            'margin_bottom' => 16,
            'margin_footer' => 6,
            'default_font_size' => 9,
        ]);

        $html = Locale::within($locale, function () use ($insights, $locale, $withMoney) {
            $rtl = config("languages.available.{$locale}.dir") === 'rtl';
            $data = $this->data($insights, $withMoney);

            return view('pdf.form-insights', [
                ...$data,
                'moneyLines' => $data['money'] ? $this->moneyLines($data['money']) : [],
                'currency' => SiteSetting::currency()['code'],
                'currencyIcon' => app(RacingPdf::class)->currencyIcon(),
                'logo' => app(RacingPdf::class)->siteLogo(),
                'siteName' => SiteSetting::siteName(),
                'locale' => $locale,
                'rtl' => $rtl,
                'd' => fn (?string $text) => PdfText::dir($text, $rtl),
            ])->render();
        });

        $title = Locale::within($locale, fn () => __('admin_form_insights.export.title'));
        $mpdf->SetTitle($title.' — '.$insights->form->name);
        $mpdf->SetAuthor(SiteSetting::siteName());
        $mpdf->SetCreator(SiteSetting::siteName());
        $mpdf->SetDirectionality(config("languages.available.{$locale}.dir") === 'rtl' ? 'rtl' : 'ltr');
        $mpdf->SetHTMLFooter(Locale::within($locale, fn () => '<div style="font-family: cairo; font-size: 7pt; color: #666666; text-align: center; border-top: 0.2mm solid #cccccc; padding-top: 1mm;">'.e(__('statement.page')).' {PAGENO} / {nbpg}</div>'));
        @ini_set('pcre.backtrack_limit', '5000000');
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    /**
     * @param  array<string, int>  $t  IncomeStatement totals
     * @return array<int, array{0: string, 1: int}>
     */
    private function moneyLines(array $t): array
    {
        return [
            [__('statement.collected'), $t['collected']],
            [__('statement.fee_and_vat'), $t['fee'] + $t['vat_on_fee']],
            [$t['vat_on_commission'] > 0 ? __('statement.commission_and_vat') : __('statement.commission'), IncomeStatement::commissionWithVat($t)],
            [__('statement.refunded'), $t['refunded']],
            [__('statement.due_to_us'), $t['due_to_us']],
            [__('statement.client_keeps'), $t['client_keeps']],
        ];
    }
}

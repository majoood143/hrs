<?php

namespace App\Services\Stables;

use App\Jobs\SendStableEmail;
use App\Jobs\SendWhatsAppMessage;
use App\Mail\StableSalesReportMail;
use App\Models\SiteSetting;
use App\Models\Stable;
use App\Services\Reports\StableStatement;
use App\Services\Sms\SmsManager;
use App\Services\WhatsApp\BilingualMessage;
use App\Support\Locale;
use App\Support\WhatsAppSettings;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * The sales report an admin can switch on for a stable: daily (yesterday) or weekly (the 7 days
 * before the chosen weekday), to its owners by the chosen channels. The figures are the statement's,
 * so they always agree with it. Times are the stables' time zone (BookingSlot::timezone()).
 */
class StableSalesReports
{
    public const LOG_TYPE = 'stable_sales_report';

    public const DEFAULTS = ['enabled' => false, 'channels' => ['email'], 'frequency' => 'weekly', 'weekday' => 'sun', 'time' => '08:00'];

    public function __construct(private readonly SmsManager $sms) {}

    /** @return array{enabled: bool, channels: list<string>, frequency: string, weekday: string, time: string} */
    public static function settings(Stable $stable): array
    {
        return [...self::DEFAULTS, ...($stable->sales_report ?? [])];
    }

    public function isDue(Stable $stable, CarbonImmutable $now): bool
    {
        $settings = self::settings($stable);

        return $settings['enabled']
            && $stable->sales_report_sent_on !== $now->toDateString()
            && $now->format('H:i') >= $settings['time']
            && ($settings['frequency'] === 'daily' || $settings['weekday'] === strtolower($now->format('D')));
    }

    public function send(Stable $stable, CarbonImmutable $now): void
    {
        $settings = self::settings($stable);
        $from = ($settings['frequency'] === 'daily' ? $now->subDay() : $now->subDays(7))->startOfDay();
        $to = $now->subDay()->endOfDay();
        $period = $from->isSameDay($to) ? $to->toDateString() : $from->toDateString().' – '.$to->toDateString();
        $totals = (new StableStatement($stable, $from, $to))->totals();
        $balance = StableStatement::balance($stable);

        // read inside each language: the stable's name is in the language being written
        $data = fn () => [
            'stable' => $stable->name,
            'period' => $period,
            'bookings' => $totals['bookings'],
            'collected' => SiteSetting::formatCurrency($totals['collected'] / 1000),
            'share' => SiteSetting::formatCurrency($totals['stable_share'] / 1000),
            'url' => url('/stable'),
        ];

        foreach ($stable->owners()->get() as $owner) {
            $locale = $owner->locale ?: config('languages.default', 'en');

            if (in_array('email', $settings['channels'], true) && filter_var($owner->email, FILTER_VALIDATE_EMAIL)) {
                SendStableEmail::dispatch($owner->email, new StableSalesReportMail($stable, $period, $totals, $balance, $locale), self::LOG_TYPE)->afterCommit();
            }

            if (in_array('sms', $settings['channels'], true) && $owner->phone && $this->sms->canSend()) {
                try {
                    $this->sms->send($owner->phone, Locale::within($locale, fn () => __('stable_statement.sms.sales_report', $data())), self::LOG_TYPE);
                } catch (Throwable $e) {
                    report($e);
                }
            }

            if (in_array('whatsapp', $settings['channels'], true) && $owner->phone && WhatsAppSettings::enabled() && WhatsAppSettings::alertEnabled(self::LOG_TYPE)) {
                SendWhatsAppMessage::dispatch($owner->phone, BilingualMessage::make(fn () => __('whatsapp.stable_sales_report', $data())), self::LOG_TYPE)->afterCommit();
            }
        }
    }
}

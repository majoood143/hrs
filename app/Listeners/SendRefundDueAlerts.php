<?php

namespace App\Listeners;

use App\Events\ServiceOrderRefundDue;
use App\Jobs\SendWhatsAppMessage;
use App\Models\User;
use App\Services\WhatsApp\RefundDueAlert;
use App\Support\Locale;
use App\Support\WhatsAppSettings;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Throwable;

/**
 * Refunds are made by hand, so a refund falling due is pushed to the admins: a WhatsApp alert to
 * the admin numbers (when that alert is on) and a notification in the admin panel for every staff
 * user who may see orders, in their own language. Auto-discovered: never register it with Event::listen.
 */
class SendRefundDueAlerts
{
    public function handle(ServiceOrderRefundDue $event): void
    {
        // the cancellation or payment is saved either way: an alert we can't send must not fail it
        try {
            $this->whatsApp($event);
        } catch (Throwable $e) {
            report($e);
        }

        try {
            $this->adminPanel($event);
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function whatsApp(ServiceOrderRefundDue $event): void
    {
        if (! WhatsAppSettings::enabled() || ! WhatsAppSettings::alertEnabled(RefundDueAlert::TYPE)) {
            return;
        }

        $recipients = WhatsAppSettings::recipients();

        if ($recipients === []) {
            return;
        }

        $message = RefundDueAlert::message($event->order, $event->reason);

        foreach ($recipients as $phone) {
            SendWhatsAppMessage::dispatch($phone, $message, RefundDueAlert::TYPE)->afterCommit();
        }
    }

    private function adminPanel(ServiceOrderRefundDue $event): void
    {
        $staff = User::query()
            ->where(fn ($query) => $query->whereNull('type')->orWhere('type', '!=', User::TYPE_STABLE_OWNER))
            ->get()
            ->filter(fn (User $user) => $user->can('ViewAny:ServiceOrder'));

        foreach ($staff as $user) {
            Locale::within($user->locale ?: (string) config('languages.default', 'en'), function () use ($event, $user) {
                // the alert's lines without its heading and link: those are the title and the button
                $lines = array_slice(RefundDueAlert::lines($event->order, $event->reason), 1, -1);

                Notification::make()
                    ->title(__('admin_service_order.refund_alert.title', ['number' => $event->order->order_number]))
                    // the body is shown as (sanitised) HTML: escape the customer's name, keep the lines apart
                    ->body(implode('<br>', array_map('e', array_filter($lines, fn (string $line) => $line !== ''))))
                    ->icon('heroicon-o-receipt-refund')
                    ->warning()
                    ->actions([
                        Action::make('view')
                            ->label(__('admin_service_order.refund_alert.view'))
                            ->url(RefundDueAlert::adminUrl($event->order))
                            ->markAsRead(),
                    ])
                    ->sendToDatabase($user);
            });
        }
    }
}

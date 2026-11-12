<?php

namespace App\Filament\Resources\ServiceOrderResource;

use App\Enums\OrderStatus;
use App\Enums\StableBookingStatus;
use App\Filament\Resources\PaymentGatewayLogResource;
use App\Filament\Stable\Resources\StableBookings\StableBookingResource;
use App\Jobs\SendStableBookingNotification;
use App\Listeners\SendStableBookingNotifications;
use App\Models\ServiceOrder;
use App\Models\SiteSetting;
use App\Models\StableBooking;
use App\Services\Notifications\OrderNotifier;
use App\Services\Orders\OrderReceiptPdf;
use App\Services\Orders\OrderRefundService;
use App\Services\Orders\RefundException;
use App\Services\Payments\PaymentGateways;
use App\Services\Stables\BookingTicketPdf;
use App\Services\Stables\BookingUnavailable;
use App\Services\Stables\StableBookingActions;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

/**
 * What an admin can do to an order, from its row in the list and from its page. The booking ones
 * stand in for a stable owner who cannot reach the panel: same rules, through the same services.
 */
class OrderActions
{
    public static function refund(): Action
    {
        return Action::make('refund')
            ->label(__('admin_service_order.actions.refund'))
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('warning')
            ->modalHeading(__('admin_service_order.actions.refund'))
            ->modalDescription(fn (ServiceOrder $record): string => __('admin_service_order.actions.refund_desc', ['max' => SiteSetting::formatCurrency($record->refundableAmount())]))
            ->visible(fn (ServiceOrder $record): bool => self::mayUpdate($record) && $record->isPaid() && $record->refundableAmount() > 0)
            ->schema(fn (ServiceOrder $record): array => [
                TextInput::make('amount')
                    ->label(__('admin_service_order.actions.refund_amount'))
                    ->numeric()
                    ->step(0.001)
                    ->minValue(0.001)
                    ->maxValue($record->refundableAmount())
                    ->default($record->refundableAmount())
                    ->required(),
                Select::make('method')
                    ->label(__('admin_service_order.actions.refund_method'))
                    ->options(['gateway' => __('admin_service_order.refund.methods.gateway'), 'manual' => __('admin_service_order.refund.methods.manual')])
                    ->default('gateway')
                    ->native(false)
                    ->required(),
                TextInput::make('reference')->label(__('admin_service_order.actions.refund_reference'))->maxLength(255),
                Textarea::make('reason')->label(__('admin_service_order.actions.refund_reason'))->rows(2),
            ])
            ->action(function (array $data, ServiceOrder $record): void {
                try {
                    app(OrderRefundService::class)->record($record, (int) round((float) $data['amount'] * 1000), $data['reason'] ?? null, auth()->id(), $data['method'], $data['reference'] ?? null);
                } catch (RefundException $e) {
                    Notification::make()->title(__('admin_service_order.notifications.refund_failed'))->body($e->getMessage())->danger()->send();

                    return;
                }

                $record->refresh();
                Notification::make()->title(__('admin_service_order.notifications.refunded'))->success()->send();
            });
    }

    public static function resend(): Action
    {
        return Action::make('resend')
            ->label(__('admin_service_order.actions.resend'))
            ->icon('heroicon-o-paper-airplane')
            ->color('gray')
            ->modalHeading(__('admin_service_order.actions.resend'))
            ->modalDescription(__('admin_service_order.actions.resend_desc'))
            ->visible(fn (ServiceOrder $record): bool => self::mayUpdate($record) && self::resendOptions($record) !== [])
            ->schema(fn (ServiceOrder $record): array => [
                Select::make('type')
                    ->label(__('admin_service_order.actions.resend_type'))
                    ->options(self::resendOptions($record))
                    ->default(array_key_first(self::resendOptions($record)))
                    ->required()
                    ->native(false),
            ])
            ->action(function (array $data, ServiceOrder $record): void {
                $channels = self::booking($record)
                    ? app(SendStableBookingNotifications::class)->notifyCustomer(self::booking($record), $data['type'], force: true)
                    : app(OrderNotifier::class)->notify($record, $data['type'], force: true);
                $record->refresh();

                $channels === []
                    ? Notification::make()->title(__('admin_service_order.notifications.resend_none'))->warning()->send()
                    : Notification::make()->title(__('admin_service_order.notifications.resent', ['channels' => implode(' + ', array_map('strtoupper', $channels))]))->success()->send();
            });
    }

    /** A booking has its own messages (date, time, the ticket), and a pay-at-the-stable one is neither paid nor free. */
    private static function resendOptions(ServiceOrder $record): array
    {
        if ($booking = self::booking($record)) {
            return array_filter([
                SendStableBookingNotification::CONFIRMED => $booking->status === StableBookingStatus::Confirmed ? __('admin_service_order.notification_types.booking_confirmed') : null,
                // only one the customer was told about: an expired checkout was never confirmed
                SendStableBookingNotification::CANCELLED => $booking->status === StableBookingStatus::Cancelled && $booking->confirmed_at ? __('admin_service_order.notification_types.booking_cancelled') : null,
            ]);
        }

        if (! $record->isPaid() && ! $record->isFree()) {
            return [];
        }

        return array_filter([
            OrderNotifier::RECEIVED => __('admin_service_order.notification_types.order_received'),
            OrderNotifier::COMPLETED => $record->status === OrderStatus::Completed ? __('admin_service_order.notification_types.order_completed') : null,
        ]);
    }

    public static function receipt(): Action
    {
        return Action::make('receipt')
            ->label(__('admin_service_order.actions.receipt'))
            ->icon('heroicon-o-document-arrow-down')
            ->color('gray')
            ->visible(fn (ServiceOrder $record): bool => app(OrderReceiptPdf::class)->available($record))
            ->action(function (ServiceOrder $record) {
                $receipts = app(OrderReceiptPdf::class);

                return response()->streamDownload(
                    fn () => print ($receipts->render($record)),
                    $receipts->filename($record),
                    ['Content-Type' => 'application/pdf'],
                );
            });
    }

    public static function ticket(): Action
    {
        return Action::make('ticket')
            ->label(__('admin_service_order.actions.download_ticket'))
            ->icon('heroicon-o-ticket')
            ->color('gray')
            ->visible(fn (ServiceOrder $record): bool => self::booking($record) && app(BookingTicketPdf::class)->available(self::booking($record)))
            ->action(function (ServiceOrder $record) {
                $tickets = app(BookingTicketPdf::class);

                return response()->streamDownload(
                    fn () => print ($tickets->render(self::booking($record))),
                    $tickets->filename(self::booking($record)),
                    ['Content-Type' => 'application/pdf'],
                );
            });
    }

    public static function paymentLogs(): Action
    {
        return Action::make('paymentLogs')
            ->label(__('admin_payment_gateway_log.navigation.plural'))
            ->icon('heroicon-o-shield-exclamation')
            ->color('gray')
            ->visible(fn (ServiceOrder $record): bool => filled($record->payment_method) && PaymentGatewayLogResource::canViewAny())
            ->url(fn (ServiceOrder $record): string => PaymentGatewayLogResource::getUrl('index', ['search' => $record->order_number]));
    }

    public static function paymentLink(): Action
    {
        return Action::make('paymentLink')
            ->label(__('admin_service_order.actions.payment_link'))
            ->icon('heroicon-o-link')
            ->color('gray')
            ->visible(fn (ServiceOrder $record): bool => $record->isPayable())
            ->url(fn (ServiceOrder $record): string => route('payment.start', $record->order_number), shouldOpenInNewTab: true);
    }

    /** Gateways often never send the browser back: asks them directly, and settles the order if it was paid. */
    public static function checkPayment(): Action
    {
        return Action::make('checkPayment')
            ->label(__('admin_service_order.actions.check_payment'))
            ->icon('heroicon-o-arrow-path')
            ->color('gray')
            ->visible(fn (ServiceOrder $record): bool => $record->isPayable() && filled($record->payment_session_id))
            ->action(function (ServiceOrder $record): void {
                app(PaymentGateways::class)->settleFromGateway($record);
                $record->refresh();

                $record->isPayable()
                    ? Notification::make()->title(__('admin_service_order.notifications.payment_not_confirmed'))->warning()->send()
                    : Notification::make()->title(__('admin_service_order.notifications.payment_settled'))->success()->send();
            });
    }

    public static function markAttended(): Action
    {
        return Action::make('attended')
            ->label(__('stable_panel.bookings.mark_attended'))
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->requiresConfirmation()
            ->visible(fn (ServiceOrder $record): bool => self::mayRunBooking($record)
                && in_array(self::booking($record)->status, [StableBookingStatus::Confirmed, StableBookingStatus::NoShow], true)
                && StableBookingResource::sessionDue(self::booking($record)))
            ->action(fn (ServiceOrder $record) => self::runBooking($record, fn (StableBookingActions $actions) => $actions->markAttended(self::booking($record))));
    }

    public static function markNoShow(): Action
    {
        return Action::make('noShow')
            ->label(__('stable_panel.bookings.mark_no_show'))
            ->icon('heroicon-o-user-minus')
            ->color('gray')
            ->requiresConfirmation()
            ->visible(fn (ServiceOrder $record): bool => self::mayRunBooking($record)
                && in_array(self::booking($record)->status, [StableBookingStatus::Confirmed, StableBookingStatus::Completed], true)
                && StableBookingResource::sessionDue(self::booking($record)))
            ->action(fn (ServiceOrder $record) => self::runBooking($record, fn (StableBookingActions $actions) => $actions->markNoShow(self::booking($record))));
    }

    public static function cancelBooking(): Action
    {
        return Action::make('cancelBooking')
            ->label(__('stable_panel.bookings.cancel'))
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (ServiceOrder $record): bool => self::mayRunBooking($record) && self::booking($record)->isActive())
            ->modalDescription(fn (ServiceOrder $record): string => __($record->isPaid() ? 'admin_service_order.actions.cancel_booking_paid_desc' : 'admin_service_order.actions.cancel_booking_desc'))
            ->schema([
                Textarea::make('reason')
                    ->label(__('stable_bookings.fields.reason'))
                    ->placeholder(__('stable_panel.placeholders.cancel_reason'))
                    ->helperText(__('stable_panel.bookings.reason_hint'))
                    ->required()
                    ->maxLength(500),
            ])
            ->action(fn (ServiceOrder $record, array $data) => self::runBooking($record, fn (StableBookingActions $actions) => $actions->cancelByStable(self::booking($record), $data['reason'])));
    }

    /** A package order also has a stable, but no booking; ordinary orders skip the query. */
    private static function booking(ServiceOrder $record): ?StableBooking
    {
        return $record->stable_id ? $record->stableBooking : null;
    }

    /** Anything that changes an order or messages its customer takes the "update" permission: viewing orders is not enough. */
    public static function mayUpdate(ServiceOrder $record): bool
    {
        return (bool) auth()->user()?->can('update', $record);
    }

    private static function mayRunBooking(ServiceOrder $record): bool
    {
        return self::booking($record) !== null && self::mayUpdate($record);
    }

    private static function runBooking(ServiceOrder $record, callable $action): void
    {
        try {
            $action(app(StableBookingActions::class));
            Notification::make()->success()->title(__('stable_panel.bookings.updated'))->send();
        } catch (BookingUnavailable $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
        }

        $record->refresh();
    }
}

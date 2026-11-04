<?php

namespace App\Filament\Stable\Pages;

use App\Enums\StableBookingStatus;
use App\Models\BookingSlot;
use App\Models\SiteSetting;
use App\Models\Stable;
use App\Models\StableBooking;
use App\Models\User;
use App\Services\Stables\BookingUnavailable;
use App\Services\Stables\StableBookingActions;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;

/**
 * Checking a customer in from their ticket: its QR code opens this page with `?reference=` (the
 * phone's camera, through `/check-in/{reference}`), or the reference is typed. One clear answer
 * (ready, collect the money first, not today, already in, cancelled...) and one button, which is
 * the Bookings list's "Mark attended" (same rule: from the session's day on).
 */
class CheckIn extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-qr-code';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.stable.pages.check-in';

    #[Url]
    public ?string $reference = null;

    public ?array $data = [];

    public bool $done = false;

    public static function getNavigationGroup(): ?string
    {
        return __('stable_panel.navigation.bookings');
    }

    public static function getNavigationLabel(): string
    {
        return __('booking_ticket.check_in.title');
    }

    public function getTitle(): string
    {
        return __('booking_ticket.check_in.title');
    }

    public static function canAccess(): bool
    {
        return Filament::getTenant() instanceof Stable;
    }

    public function mount(): void
    {
        $this->reference = static::parse($this->reference);
        $this->form->fill(['reference' => $this->reference]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                TextInput::make('reference')
                    ->label(__('booking_ticket.check_in.reference'))
                    ->placeholder(__('booking_ticket.check_in.reference_placeholder'))
                    ->helperText(__('booking_ticket.check_in.reference_hint'))
                    ->required()
                    ->maxLength(300)
                    ->autofocus()
                    ->extraInputAttributes(['dir' => 'ltr', 'autocomplete' => 'off', 'autocapitalize' => 'characters']),
            ]);
    }

    public function find(): void
    {
        $this->reference = static::parse($this->form->getState()['reference'] ?? null);
        $this->form->fill(['reference' => $this->reference]);
        $this->done = false;
    }

    public function another(): void
    {
        $this->reference = null;
        $this->done = false;
        $this->form->fill(['reference' => null]);
    }

    /** A typed or pasted reference, or a ticket link: just the BK-XXXXXXXX. */
    public static function parse(?string $text): ?string
    {
        $text = strtoupper(trim((string) $text));

        return preg_match('/BK-[A-Z0-9]{8}/', $text, $m) ? $m[0] : ($text === '' ? null : $text);
    }

    public function booking(): ?StableBooking
    {
        if (! $this->reference) {
            return null;
        }

        /** @var Stable $stable */
        $stable = Filament::getTenant();

        return $stable->bookings()->with(['slot.trainer', 'offering', 'order'])->where('reference', $this->reference)->first();
    }

    /** The same booking at another of this person's stables, so they can switch to it. */
    public function elsewhere(): ?StableBooking
    {
        $user = Auth::user();
        $booking = $this->reference ? StableBooking::query()->with('stable')->where('reference', $this->reference)->first() : null;

        return $booking?->stable && $user instanceof User && $user->canAccessTenant($booking->stable) ? $booking : null;
    }

    /**
     * What the person at the gate needs to know, and whether "Check in" is offered.
     *
     * @return array{tone: string, icon: string, text: string, note: ?string, can: bool}
     */
    public static function state(StableBooking $booking): array
    {
        $due = $booking->slot && $booking->slot->date->toDateString() <= BookingSlot::today();
        $collect = $booking->payment_option === 'at_stable' && ! $booking->order?->isPaid() && in_array($booking->status, [StableBookingStatus::Confirmed, StableBookingStatus::NoShow], true)
            ? __('booking_ticket.check_in.collect', ['total' => SiteSetting::formatCurrency((float) $booking->order?->total)])
            : null;
        $s = fn (string $tone, string $icon, string $text, bool $can = false) => ['tone' => $tone, 'icon' => $icon, 'text' => $text, 'note' => $can ? $collect : null, 'can' => $can];

        return match (true) {
            $booking->status === StableBookingStatus::Cancelled => $s('danger', 'heroicon-o-x-circle', __('booking_ticket.check_in.cancelled')),
            $booking->status === StableBookingStatus::Pending => $s('warning', 'heroicon-o-clock', __('booking_ticket.check_in.pending')),
            $booking->status === StableBookingStatus::Completed => $s('success', 'heroicon-o-check-badge', __('booking_ticket.check_in.already', [
                'time' => $booking->attended_at?->setTimezone(BookingSlot::timezone())->locale(app()->getLocale())->translatedFormat('j M, H:i') ?? '',
            ])),
            ! $due => $s('warning', 'heroicon-o-calendar', __('booking_ticket.check_in.not_yet', [
                'date' => $booking->slot?->startsAt()->locale(app()->getLocale())->translatedFormat('l j F') ?? '',
            ])),
            $booking->status === StableBookingStatus::NoShow => $s('warning', 'heroicon-o-user-minus', __('booking_ticket.check_in.no_show'), true),
            default => $s('success', 'heroicon-o-check-circle', __('booking_ticket.check_in.ready'), true),
        };
    }

    public function checkIn(StableBookingActions $actions): void
    {
        $booking = $this->booking();

        if (! $booking || ! static::state($booking)['can']) {
            return;
        }

        try {
            $actions->markAttended($booking);
            $this->done = true;
        } catch (BookingUnavailable $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
        }
    }
}

<?php

namespace App\Http\Controllers\Site;

use App\Filament\Stable\Pages\CheckIn;
use App\Http\Controllers\Controller;
use App\Models\StableBooking;
use App\Models\User;
use App\Services\Orders\OrderReceiptPdf;
use App\Services\Stables\BookingTicketPdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * A booking's ticket: the PDF (a signed link, from the account pages and the app) and the page its
 * QR code opens. The stable's own staff go straight to the stable panel's check-in; anyone else sees
 * which stable the ticket is for and nothing personal.
 */
class BookingTicketController extends Controller
{
    public function download(string $reference, BookingTicketPdf $tickets): Response
    {
        $booking = StableBooking::query()->where('reference', $reference)->firstOrFail();

        abort_unless($tickets->available($booking), 404);

        return response($tickets->render($booking), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$tickets->filename($booking).'"',
            'X-Robots-Tag' => 'noindex',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** The booking's receipt, from the signed link the app gets (`receipt_url`). */
    public function receipt(Request $request, string $reference, OrderReceiptPdf $receipts): Response
    {
        $order = StableBooking::query()->where('reference', $reference)->firstOrFail()->order;

        abort_unless($order && $receipts->available($order), 404);

        return response($receipts->render($order, $request->query('lang')), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$receipts->filename($order).'"',
            'X-Robots-Tag' => 'noindex',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function checkIn(string $reference): View|RedirectResponse
    {
        $booking = StableBooking::query()->with(['stable', 'slot', 'order'])->where('reference', strtoupper($reference))->firstOrFail();
        $staffUrl = CheckIn::getUrl(['reference' => $booking->reference], panel: 'stable', tenant: $booking->stable);
        $user = Auth::guard('web')->user();

        if ($user instanceof User && $booking->stable && $user->canAccessTenant($booking->stable)) {
            return redirect()->to($staffUrl);
        }

        return view('site.bookings.check-in', [
            'booking' => $booking,
            'staffUrl' => $staffUrl,
            'seoTitle' => __('booking_ticket.landing.title', ['reference' => $booking->reference]),
            'noindex' => true,
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\ShopSetting;
use App\Services\BookingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class BookingController extends Controller
{
    /**
     * Show a booking to whoever holds its private link.
     */
    public function show(Booking $booking): View
    {
        return view('bookings.show', [
            'booking' => $booking->load(['staffMember', 'items', 'discounts']),
            'shop' => ShopSetting::current(),
        ]);
    }

    /**
     * Let the customer cancel their own booking from its private link.
     */
    public function cancel(Booking $booking, BookingService $bookings): RedirectResponse
    {
        if (! $booking->canBeCancelledByCustomer()) {
            return to_route('bookings.show', $booking->access_token)
                ->with('booking_error', "This booking can't be cancelled online any more. Call us if you need help.");
        }

        try {
            $bookings->cancel($booking, 'customer');
        } catch (BookingException $exception) {
            return to_route('bookings.show', $booking->access_token)->with('booking_error', $exception->getMessage());
        }

        return to_route('bookings.show', $booking->access_token);
    }
}

<?php

namespace App\Mail;

use App\Models\Booking;
use App\Models\ShopSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingConfirmed extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Booking $booking) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your booking on '.$this->booking->localStartsAt()->isoFormat('dddd D MMMM [at] HH:mm'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.booking-confirmed',
            with: [
                'shop' => ShopSetting::current(),
                'manageUrl' => route('bookings.show', $this->booking->access_token),
            ],
        );
    }
}

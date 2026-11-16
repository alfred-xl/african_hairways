@php
    use App\Enums\BookingStatus;
    use App\Support\Money;

    $startsAt = $booking->localStartsAt();
    $isUpcoming = $booking->status === BookingStatus::Booked && $booking->starts_at->isFuture();
@endphp

<x-layouts.app title="Your booking">
    <x-slot:head>
        {{-- The address of this page is the key to the booking, so keep it out of search engines and referrer headers. --}}
        <meta name="robots" content="noindex, nofollow">
        <meta name="referrer" content="no-referrer">
    </x-slot:head>

    <div class="mx-auto grid max-w-xl gap-4">
        @if (session('booking_error'))
            <p class="rounded-[10px] bg-soft px-3.5 py-2.5 text-sm" role="alert">{{ session('booking_error') }}</p>
        @endif

        <section class="card {{ $isUpcoming ? 'border-accent bg-soft' : '' }}">
            @if ($booking->status === BookingStatus::Cancelled)
                <h2>Booking cancelled</h2>
                <p class="mt-2">This appointment on {{ $startsAt->isoFormat('dddd D MMMM') }} at {{ $startsAt->format('H:i') }} has been cancelled. Nothing is owed.</p>
            @elseif ($booking->status === BookingStatus::Completed)
                <h2>Thanks for coming in</h2>
                <p class="mt-2">{{ $booking->customer_name }}, you saw {{ $booking->staffMember->name }} on {{ $startsAt->isoFormat('dddd D MMMM') }} at {{ $startsAt->format('H:i') }}.</p>
            @elseif ($booking->status === BookingStatus::NoShow)
                <h2>Missed appointment</h2>
                <p class="mt-2">This appointment on {{ $startsAt->isoFormat('dddd D MMMM') }} at {{ $startsAt->format('H:i') }} was marked as missed.</p>
            @elseif ($isUpcoming)
                <h2>You're booked</h2>
                <p class="mt-2">{{ $booking->customer_name }}, see you on <b>{{ $startsAt->isoFormat('dddd D MMMM') }}</b> at <b>{{ $startsAt->format('H:i') }}</b> with <b>{{ $booking->staffMember->name }}</b>.</p>
            @else
                <h2>Your booking</h2>
                <p class="mt-2">{{ $booking->customer_name }}, your appointment was on {{ $startsAt->isoFormat('dddd D MMMM') }} at {{ $startsAt->format('H:i') }} with {{ $booking->staffMember->name }}.</p>
            @endif

            @if (session('booking_notice'))
                <p class="mt-2 text-sm text-muted">{{ session('booking_notice') }}</p>
            @endif

            <dl class="summary-list">
                @foreach ($booking->items as $item)
                    <dt>{{ $item->is_addon ? '+ ' : '' }}{{ $item->name }}</dt>
                    <dd>{{ Money::format($item->price_pence) }}</dd>
                @endforeach

                @foreach ($booking->discounts as $discount)
                    <dt class="!text-good">{{ $discount->label }}</dt>
                    <dd class="text-good">−{{ Money::format($discount->amount_pence) }}</dd>
                @endforeach

                <dt>Time in the chair</dt>
                <dd>{{ $booking->duration_minutes }} min</dd>

                <dt>Booking reference</dt>
                <dd>{{ $booking->reference }}</dd>
            </dl>

            <div class="total">
                <span>{{ $booking->pay_staff_directly ? 'To pay '.$booking->staffMember->name : 'To pay in the shop' }}</span>
                <b>{{ Money::format($booking->total_pence) }}</b>
            </div>

            @if ($booking->notes)
                <p class="mt-3 text-sm"><span class="text-muted">Your notes:</span> {{ $booking->notes }}</p>
            @endif
        </section>

        @if ($isUpcoming)
            <section class="card grid gap-3">
                <h3>Change of plan?</h3>
                <p class="text-sm text-muted">
                    Save this page: it's your private link to this booking, and anyone who has it can cancel the appointment.
                    @if ($booking->email) We've also emailed it to {{ $booking->email }}. @endif
                    To move your appointment, cancel it here and book a new time.
                    @if ($shop->phone) Running late? Call {{ $shop->phone }}. @endif
                </p>

                <div class="flex flex-wrap items-start gap-2">
                    <a href="{{ route('book') }}" class="btn btn-ghost">Book another appointment</a>

                    {{-- A native disclosure, so cancelling needs two deliberate taps and no JavaScript. --}}
                    <details class="group">
                        <summary class="btn btn-ghost cursor-pointer list-none group-open:hidden [&::-webkit-details-marker]:hidden">Cancel this booking</summary>

                        <form method="POST" action="{{ route('bookings.cancel', $booking->access_token) }}" class="flex flex-wrap items-center gap-2">
                            @csrf
                            <span class="w-full text-sm">Cancel your {{ $startsAt->format('H:i') }} appointment on {{ $startsAt->isoFormat('dddd D MMMM') }}?</span>
                            <button type="submit" class="btn">Yes, cancel it</button>
                        </form>
                    </details>
                </div>
            </section>
        @else
            <a href="{{ route('book') }}" class="btn justify-self-start">Book an appointment</a>
        @endif
    </div>
</x-layouts.app>

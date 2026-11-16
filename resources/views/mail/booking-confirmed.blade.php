<x-mail::message>
# You're booked, {{ $booking->customer_name }}

See you on **{{ $booking->localStartsAt()->isoFormat('dddd D MMMM') }}** at **{{ $booking->localStartsAt()->format('H:i') }}** with **{{ $booking->staffMember->name }}**.

<x-mail::table>
| | |
|:--|--:|
@foreach ($booking->items as $item)
| {{ $item->is_addon ? '+ ' : '' }}{{ $item->name }} | {{ \App\Support\Money::format($item->price_pence) }} |
@endforeach
@foreach ($booking->discounts as $discount)
| {{ $discount->label }} | −{{ \App\Support\Money::format($discount->amount_pence) }} |
@endforeach
| Time in the chair | {{ $booking->duration_minutes }} min |
| **{{ $booking->pay_staff_directly ? 'To pay '.$booking->staffMember->name : 'To pay in the shop' }}** | **{{ \App\Support\Money::format($booking->total_pence) }}** |
</x-mail::table>

Booking reference: **{{ $booking->reference }}**

<x-mail::button :url="$manageUrl">
View or cancel your booking
</x-mail::button>

Keep this link private: anyone who has it can see and cancel this booking.

@if ($shop->phone)
Running late? Call us on {{ $shop->phone }}.
@endif

{{ $shop->shop_name }}@if ($shop->address)<br>{{ $shop->address }}@endif

</x-mail::message>

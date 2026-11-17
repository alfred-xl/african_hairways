@php
    use App\Enums\BookingStatus;
    use App\Support\Money;
    use App\Support\UkMobile;

    $statusColors = [
        BookingStatus::Booked->value => 'info',
        BookingStatus::Completed->value => 'success',
        BookingStatus::Cancelled->value => 'gray',
        BookingStatus::NoShow->value => 'warning',
    ];
@endphp

<x-filament-panels::page>
    {{-- The admin panel ships its own compiled CSS, so the diary's few layout rules live here. --}}
    <style>
        .diary-bar { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; }
        .diary-bar input[type="date"] { border: 1px solid color-mix(in srgb, currentColor 20%, transparent); border-radius: .5rem; padding: .4rem .6rem; background: transparent; color: inherit; font: inherit; }
        .diary-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: .75rem; }
        .diary-stat b { display: block; font-size: 1.6rem; font-weight: 700; line-height: 1.2; font-variant-numeric: tabular-nums; }
        .diary-stat span { font-size: .85rem; opacity: .7; }
        .diary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(270px, 1fr)); gap: 1rem; align-items: start; }
        .diary-list { display: grid; gap: .6rem; }
        .diary-slot { display: grid; gap: .25rem; padding: .7rem; border-radius: .6rem; border: 1px solid color-mix(in srgb, currentColor 14%, transparent); border-left: 5px solid var(--staff-color, #1d3557); font-size: .92rem; }
        .diary-slot.is-finished { opacity: .6; }
        .diary-slot.is-block { border-left-color: color-mix(in srgb, currentColor 35%, transparent); border-style: dashed; border-left-style: solid; }
        .diary-slot .time { font-weight: 700; font-variant-numeric: tabular-nums; }
        .diary-slot .row { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .4rem; }
        .diary-slot .muted { opacity: .7; font-size: .86rem; }
        .diary-slot a { text-decoration: underline; }
        .diary-actions { display: flex; flex-wrap: wrap; gap: .4rem; margin-top: .3rem; }
        .diary-empty { opacity: .65; font-size: .92rem; }
    </style>

    <div class="diary-bar">
        <x-filament::button color="gray" size="sm" wire:click="previousDay" icon="heroicon-m-chevron-left">Previous</x-filament::button>
        <input type="date" wire:model.live="date" aria-label="Diary date">
        <x-filament::button color="gray" size="sm" wire:click="nextDay" icon="heroicon-m-chevron-right" icon-position="after">Next</x-filament::button>
        @unless ($isToday)
            <x-filament::button color="gray" size="sm" wire:click="today">Today</x-filament::button>
        @endunless
    </div>

    <div class="diary-stats">
        <x-filament::section compact>
            <div class="diary-stat"><b>{{ $stats['appointments'] }}</b><span>Appointments</span></div>
        </x-filament::section>
        <x-filament::section compact>
            <div class="diary-stat"><b>{{ $stats['completed'] }}</b><span>Completed</span></div>
        </x-filament::section>
        <x-filament::section compact>
            <div class="diary-stat"><b>{{ Money::format($stats['takingsPence']) }}</b><span>Expected shop takings (chair renters not included)</span></div>
        </x-filament::section>
    </div>

    <div class="diary-grid">
        @foreach ($columns as $column)
            @php($staffMember = $column['staffMember'])

            <x-filament::section wire:key="diary-staff-{{ $staffMember->id }}">
                <x-slot name="heading">{{ $staffMember->name }}</x-slot>

                @if ($staffMember->is_chair_renter)
                    <x-slot name="description">Chair renter · paid directly</x-slot>
                @endif

                <div class="diary-list" style="--staff-color: {{ $staffMember->color }}">
                    @foreach ($column['blocks'] as $block)
                        @php($from = $block->starts_at->setTimezone($day->timezone))
                        @php($until = $block->ends_at->setTimezone($day->timezone))

                        <div class="diary-slot is-block" wire:key="diary-block-{{ $staffMember->id }}-{{ $block->id }}">
                            <div class="row">
                                <span class="time">
                                    {{ $from->lte($day) && $until->gte($day->addDay()) ? 'All day' : ($from->lt($day) ? 'From earlier' : $from->format('H:i')).' – '.($until->gt($day->addDay()) ? 'later' : $until->format('H:i')) }}
                                </span>
                                <x-filament::badge color="gray">{{ $block->staff_member_id === null ? 'Shop closed' : $block->type->label() }}</x-filament::badge>
                            </div>
                            @if ($block->reason)
                                <span class="muted">{{ $block->reason }}</span>
                            @endif
                        </div>
                    @endforeach

                    @forelse ($column['bookings'] as $booking)
                        <div class="diary-slot {{ $booking->status === BookingStatus::Booked ? '' : 'is-finished' }}" wire:key="diary-booking-{{ $booking->id }}">
                            <div class="row">
                                <span class="time">{{ $booking->localStartsAt()->format('H:i') }} – {{ $booking->localEndsAt()->format('H:i') }}</span>
                                <x-filament::badge :color="$statusColors[$booking->status->value]">{{ $booking->status->label() }}</x-filament::badge>
                            </div>

                            <span>
                                <strong>{{ $booking->customer_name }}</strong>
                                · <a href="tel:{{ $booking->customer->phone }}">{{ UkMobile::display($booking->customer->phone) }}</a>
                            </span>

                            <span class="muted">
                                {{ $booking->serviceSummary() }}
                                · {{ $booking->source->label() }}
                                @foreach ($booking->discounts as $discount)
                                    · {{ $discount->type === \App\Enums\DiscountType::PromoCode && $booking->promoCode ? $booking->promoCode->code : $discount->label }}
                                @endforeach
                            </span>

                            @if ($booking->notes)
                                <span class="muted">“{{ $booking->notes }}”</span>
                            @endif

                            <div class="row">
                                <strong>{{ Money::format($booking->total_pence) }}</strong>
                                <span class="muted">{{ $booking->reference }}</span>
                            </div>

                            @if ($booking->status === BookingStatus::Booked)
                                <div class="diary-actions">
                                    {{ ($this->completeAction)(['booking' => $booking->id]) }}
                                    {{ ($this->noShowAction)(['booking' => $booking->id]) }}
                                    {{ ($this->cancelAction)(['booking' => $booking->id]) }}
                                </div>
                            @endif
                        </div>
                    @empty
                        @if ($column['blocks']->isEmpty())
                            <p class="diary-empty">{{ $column['isWorking'] ? 'No appointments yet.' : 'Day off' }}</p>
                        @endif
                    @endforelse
                </div>
            </x-filament::section>
        @endforeach
    </div>
</x-filament-panels::page>

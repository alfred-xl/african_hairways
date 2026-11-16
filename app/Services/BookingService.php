<?php

namespace App\Services;

use App\Data\NewBooking;
use App\Data\Quote;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\DiscountType;
use App\Exceptions\BookingException;
use App\Exceptions\PromoCodeRejectedException;
use App\Exceptions\SlotUnavailableException;
use App\Mail\BookingConfirmed;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\PromoCode;
use App\Models\Service;
use App\Models\ShopSetting;
use App\Models\StaffMember;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class BookingService
{
    public function __construct(
        private AvailabilityService $availability,
        private PricingService $pricing,
        private PromoCodeChecker $promoCodes,
    ) {}

    /**
     * Create a booking, enforcing every rule on the server.
     *
     * The staff member's row is locked for the length of the transaction, so two people
     * confirming the same time are handled one after the other: the second one sees the
     * first booking and is refused.
     *
     * @throws BookingException When the booking is refused. The message is safe to show.
     */
    public function create(NewBooking $data): Booking
    {
        $staffMember = StaffMember::query()->active()->with('serviceCategories')->find($data->staffMemberId)
            ?? throw new BookingException('That barber or stylist is no longer taking bookings. Choose someone else.');

        $main = $this->mainService($staffMember, $data->serviceId);
        $extras = $this->extras($staffMember, $main, $data->extraServiceIds);

        $booking = DB::transaction(function () use ($data, $staffMember, $main, $extras): Booking {
            // Serialise bookings per staff member. Lock order is always staff member, customer, promo code.
            StaffMember::query()->whereKey($staffMember->id)->lockForUpdate()->first();

            $settings = ShopSetting::current();
            $isOnline = $data->source === BookingSource::Online;
            $startsAt = $data->startsAt->utc();
            $duration = $main->duration_minutes + $extras->sum('duration_minutes');

            $this->ensureTimeIsFree($staffMember, $data, $duration);

            $customer = $this->lockCustomer($data);

            if ($isOnline) {
                $this->ensureWithinUpcomingLimit($customer, $settings);
            }

            $secondVisit = $this->earnsSecondVisitDiscount($customer, $settings);
            $promoCode = $secondVisit ? null : $this->resolvePromoCode($data, $customer);

            $quote = $this->pricing->quote($main, $extras, $startsAt, $settings, $promoCode, $secondVisit, $data->source);

            $booking = Booking::create([
                'reference' => $this->uniqueReference(),
                'access_token' => Str::random(40),
                'staff_member_id' => $staffMember->id,
                'customer_id' => $customer->id,
                'customer_name' => $data->customerName,
                'email' => $data->email,
                'notes' => $data->notes,
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->addMinutes($duration),
                'duration_minutes' => $duration,
                'status' => BookingStatus::Booked,
                'source' => $data->source,
                'subtotal_pence' => $quote->subtotalPence,
                'discount_pence' => $quote->discountPence(),
                'total_pence' => $quote->totalPence,
                'pay_staff_directly' => $staffMember->is_chair_renter,
                'created_by_user_id' => $data->createdBy?->id,
            ]);

            $this->storeLines($booking, $quote, $promoCode, $customer);

            return $booking;
        });

        $this->sendConfirmation($booking);

        return $booking;
    }

    /**
     * @throws BookingException
     */
    public function complete(Booking $booking): void
    {
        $this->transition($booking, BookingStatus::Completed, ['completed_at' => now()]);
    }

    /**
     * @throws BookingException
     */
    public function markNoShow(Booking $booking): void
    {
        $this->transition($booking, BookingStatus::NoShow);
    }

    /**
     * @param  string  $cancelledBy  "customer" or "staff"
     *
     * @throws BookingException
     */
    public function cancel(Booking $booking, string $cancelledBy): void
    {
        $this->transition($booking, BookingStatus::Cancelled, ['cancelled_at' => now(), 'cancelled_by' => $cancelledBy]);
    }

    /**
     * Move a booking out of the "booked" state. Finished, cancelled and no-show bookings are final.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function transition(Booking $booking, BookingStatus $status, array $attributes = []): void
    {
        DB::transaction(function () use ($booking, $status, $attributes): void {
            $locked = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== BookingStatus::Booked) {
                throw new BookingException('This booking is already marked as '.mb_strtolower($locked->status->label()).'.');
            }

            $locked->update(['status' => $status, ...$attributes]);
        });

        $booking->refresh();
    }

    private function mainService(StaffMember $staffMember, int $serviceId): Service
    {
        $service = Service::query()->active()->where('is_addon_only', false)->find($serviceId);

        if ($service === null || ! $staffMember->offersCategory($service->service_category_id)) {
            throw new BookingException("{$staffMember->name} doesn't offer that service. Choose another service.");
        }

        return $service;
    }

    /**
     * @param  list<int>  $extraServiceIds
     * @return Collection<int, Service>
     */
    private function extras(StaffMember $staffMember, Service $main, array $extraServiceIds): Collection
    {
        $ids = array_values(array_unique(array_map(intval(...), $extraServiceIds)));

        if ($ids === []) {
            return new Collection;
        }

        $extras = Service::query()->active()->ordered()
            ->where('available_as_addon', true)
            ->whereKeyNot($main->id)
            ->findMany($ids);

        $allOffered = $extras->every(fn (Service $extra): bool => $staffMember->offersCategory($extra->service_category_id));

        if ($extras->count() !== count($ids) || ! $allOffered) {
            throw new BookingException("One of those extras isn't available with {$staffMember->name}. Review your extras and try again.");
        }

        return $extras;
    }

    private function ensureTimeIsFree(StaffMember $staffMember, NewBooking $data, int $duration): void
    {
        if ($data->source === BookingSource::Online) {
            if (! $this->availability->isBookable($staffMember, $data->startsAt, $duration)) {
                throw new SlotUnavailableException('That time is no longer free. Pick another slot.');
            }

            return;
        }

        // Staff may book outside the public rules (a walk-in right now, a late finish), but never over another booking.
        if ($this->availability->clashesWithBooking($staffMember, $data->startsAt, $duration)) {
            throw new SlotUnavailableException("That time clashes with another booking for {$staffMember->name}.");
        }
    }

    private function lockCustomer(NewBooking $data): Customer
    {
        $customer = Customer::firstOrCreate(['phone' => $data->phone], [
            'name' => $data->customerName,
            'email' => $data->email,
        ]);

        $customer = Customer::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();

        $customer->update([
            'name' => $data->customerName,
            'email' => $data->email ?? $customer->email,
        ]);

        return $customer;
    }

    private function ensureWithinUpcomingLimit(Customer $customer, ShopSetting $settings): void
    {
        $upcoming = $customer->bookings()
            ->where('status', BookingStatus::Booked)
            ->where('source', BookingSource::Online)
            ->where('starts_at', '>', now())
            ->count();

        if ($upcoming >= config('shop.max_upcoming_online_bookings')) {
            throw new BookingException(trim("That's the most bookings we can take online for one mobile number. Call us to book more. {$settings->phone}"));
        }
    }

    /**
     * Every second visit is discounted: the booking made after an odd number of completed visits.
     * Only one upcoming booking can hold the discount at a time, so booking twice in a row doesn't double it.
     */
    private function earnsSecondVisitDiscount(Customer $customer, ShopSetting $settings): bool
    {
        if ($settings->second_visit_percent < 1 || $customer->completedVisitsCount() % 2 !== 1) {
            return false;
        }

        return ! $customer->bookings()
            ->where('status', BookingStatus::Booked)
            ->whereHas('discounts', fn (Builder $query) => $query->where('type', DiscountType::SecondVisit))
            ->exists();
    }

    private function resolvePromoCode(NewBooking $data, Customer $customer): ?PromoCode
    {
        if (blank($data->promoCode)) {
            return null;
        }

        // Locked so a code with a usage limit can't be over-redeemed by simultaneous bookings.
        $promoCode = PromoCode::query()
            ->where('code', PromoCode::normaliseCode($data->promoCode))
            ->lockForUpdate()
            ->first();

        $rejection = $promoCode === null
            ? PromoCodeChecker::NOT_VALID
            : $this->promoCodes->generalRejection($promoCode, $data->startsAt)
                ?? $this->promoCodes->customerRejection($promoCode, $customer);

        if ($rejection !== null) {
            throw new PromoCodeRejectedException($rejection);
        }

        return $promoCode;
    }

    private function storeLines(Booking $booking, Quote $quote, ?PromoCode $promoCode, Customer $customer): void
    {
        foreach ($quote->lines as $line) {
            $booking->items()->create([
                'service_id' => $line->serviceId,
                'name' => $line->name,
                'price_pence' => $line->pricePence,
                'duration_minutes' => $line->durationMinutes,
                'is_addon' => $line->isAddon,
            ]);
        }

        foreach ($quote->discounts as $discount) {
            $booking->discounts()->create([
                'type' => $discount->type,
                'label' => $discount->label,
                'amount_pence' => $discount->amountPence,
            ]);
        }

        $promoDiscount = $quote->discountOfType(DiscountType::PromoCode);

        if ($promoCode !== null && $promoDiscount !== null) {
            $booking->update(['promo_code_id' => $promoCode->id]);

            $booking->promoCodeRedemption()->create([
                'promo_code_id' => $promoCode->id,
                'customer_id' => $customer->id,
                'amount_pence' => $promoDiscount->amountPence,
            ]);
        }
    }

    private function uniqueReference(): string
    {
        do {
            // No 0/O or 1/I, so a reference read out over the phone can't be misheard.
            $reference = 'AH-'.collect(range(1, 6))
                ->map(fn (): string => 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'[random_int(0, 31)])
                ->implode('');
        } while (Booking::where('reference', $reference)->exists());

        return $reference;
    }

    /**
     * Email the confirmation when an address was given. A mail failure must never undo a saved booking.
     */
    private function sendConfirmation(Booking $booking): void
    {
        if (blank($booking->email)) {
            return;
        }

        rescue(fn () => Mail::to($booking->email)->send(new BookingConfirmed($booking)));
    }
}

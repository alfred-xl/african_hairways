<?php

namespace App\Livewire;

use App\Data\NewBooking;
use App\Data\Quote;
use App\Exceptions\BookingException;
use App\Exceptions\PromoCodeRejectedException;
use App\Exceptions\SlotUnavailableException;
use App\Models\PromoCode;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ShopSetting;
use App\Models\StaffMember;
use App\Rules\UkMobileNumber;
use App\Services\AvailabilityService;
use App\Services\BookingService;
use App\Services\PricingService;
use App\Services\PromoCodeChecker;
use App\Support\UkMobile;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The customer booking page. Everything the browser sends (ids, date, time, code) is treated as
 * untrusted: the selection is re-checked on every request and BookingService has the final say.
 * Only ids and the customer's own typed details are kept in component state.
 */
#[Title('Book an appointment')]
class BookAppointment extends Component
{
    public ?int $staffId = null;

    public ?int $categoryId = null;

    public ?int $serviceId = null;

    /** @var list<int|string> */
    public array $extraIds = [];

    public ?string $date = null;

    public ?string $time = null;

    public string $name = '';

    public string $phone = '';

    public string $email = '';

    public string $notes = '';

    public string $promoInput = '';

    public ?string $promoCode = null;

    public function mount(): void
    {
        $this->fitSelection();
    }

    /**
     * Re-check the selection after any change from the browser.
     */
    public function updated(string $property): void
    {
        if (in_array(explode('.', $property)[0], ['staffId', 'categoryId', 'serviceId', 'extraIds', 'date', 'time'], strict: true)) {
            $this->fitSelection();
        }
    }

    public function selectStaff(int $staffId): void
    {
        $this->staffId = $staffId;
        $this->time = null;
        $this->fitSelection();
    }

    public function selectCategory(int $categoryId): void
    {
        $this->categoryId = $categoryId;
        $this->fitSelection();
    }

    public function selectDate(string $date): void
    {
        $this->date = $date;
        $this->time = null;
        $this->fitSelection();
    }

    public function selectTime(string $time): void
    {
        $this->time = $time;
        $this->fitSelection();
    }

    public function applyPromo(PromoCodeChecker $checker): void
    {
        $this->resetErrorBag('promoInput');
        $code = PromoCode::normaliseCode($this->promoInput);

        if ($code === '') {
            $this->promoCode = null;

            return;
        }

        // Stops the box being used to guess codes.
        if (! RateLimiter::attempt('promo:'.request()->ip(), maxAttempts: 10, callback: fn () => true, decaySeconds: 60)) {
            $this->addError('promoInput', 'Too many tries. Wait a minute and try again.');

            return;
        }

        $promoCode = mb_strlen($code) <= 30 ? PromoCode::where('code', $code)->first() : null;
        $rejection = $promoCode === null
            ? PromoCodeChecker::NOT_VALID
            : $checker->generalRejection($promoCode, $this->startsAt());

        if ($rejection !== null) {
            $this->promoCode = null;
            $this->addError('promoInput', $rejection);

            return;
        }

        $this->promoCode = $promoCode->code;
        $this->promoInput = $promoCode->code;
        unset($this->appliedPromo, $this->quote);
    }

    public function removePromo(): void
    {
        $this->promoCode = null;
        $this->promoInput = '';
        $this->resetErrorBag(['promoInput', 'booking']);
        unset($this->appliedPromo, $this->quote);
    }

    public function confirm(BookingService $bookings): void
    {
        $this->resetErrorBag();

        $hadChosenTime = $this->time !== null;
        $this->fitSelection();

        if ($hadChosenTime && $this->time === null) {
            $this->addError('booking', 'Someone just took that time. Pick another slot.');

            return;
        }

        $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:60'],
            'phone' => ['required', 'string', 'max:25', new UkMobileNumber],
            'email' => ['nullable', 'email', 'max:254'],
            'notes' => ['nullable', 'string', 'max:300'],
        ], [
            'name.required' => "Add your name so your barber knows who's coming.",
            'name.min' => "Add your name so your barber knows who's coming.",
            'phone.required' => 'Add your mobile number so we can reach you about your booking.',
            'email.email' => 'Check the email address, or leave it empty.',
        ]);

        $startsAt = $this->startsAt();

        if ($this->selectedStaff === null || $this->selectedService === null || $startsAt === null) {
            $this->addError('booking', 'Pick a time to continue.');

            return;
        }

        if (! RateLimiter::attempt('book:'.request()->ip(), maxAttempts: 10, callback: fn () => true, decaySeconds: 600)) {
            $this->addError('booking', "Too many bookings from this connection. Wait a few minutes, or call us on {$this->settings->phone}.");

            return;
        }

        try {
            $booking = $bookings->create(new NewBooking(
                staffMemberId: $this->selectedStaff->id,
                serviceId: $this->selectedService->id,
                extraServiceIds: $this->selectedExtras->modelKeys(),
                startsAt: $startsAt,
                customerName: trim($this->name),
                phone: UkMobile::normalise($this->phone),
                email: trim($this->email) ?: null,
                notes: trim($this->notes) ?: null,
                promoCode: $this->promoCode,
            ));
        } catch (SlotUnavailableException) {
            $this->time = null;
            $this->fitSelection();
            $this->addError('booking', 'Someone just took that time. Pick another slot.');

            return;
        } catch (PromoCodeRejectedException $exception) {
            $this->addError('promoInput', $exception->getMessage());
            $this->addError('booking', $exception->getMessage());

            return;
        } catch (BookingException $exception) {
            $this->addError('booking', $exception->getMessage());

            return;
        }

        if ($this->promoCode !== null && $booking->promo_code_id === null) {
            session()->flash('booking_notice', "{$this->promoCode} wasn't used, because it can't be combined with your every-second-visit discount.");
        }

        $this->redirectRoute('bookings.show', $booking->access_token);
    }

    public function render(): View
    {
        return view('livewire.book-appointment');
    }

    #[Computed]
    public function settings(): ShopSetting
    {
        return ShopSetting::current();
    }

    /**
     * @return Collection<int, StaffMember>
     */
    #[Computed]
    public function staffMembers(): Collection
    {
        return StaffMember::query()->active()->ordered()->with(['serviceCategories', 'workingHours'])->get();
    }

    #[Computed]
    public function selectedStaff(): ?StaffMember
    {
        return $this->staffMembers->find($this->staffId);
    }

    /**
     * The selected staff member's categories that have at least one bookable main service.
     *
     * @return Collection<int, ServiceCategory>
     */
    #[Computed]
    public function categories(): Collection
    {
        if ($this->selectedStaff === null) {
            return new Collection;
        }

        $withMainServices = Service::query()->active()->where('is_addon_only', false)->distinct()->pluck('service_category_id');

        return $this->selectedStaff->serviceCategories
            ->whereIn('id', $withMainServices)
            ->sortBy(['sort_order', 'id'])
            ->values();
    }

    /**
     * Main services in the open category tab.
     *
     * @return Collection<int, Service>
     */
    #[Computed]
    public function services(): Collection
    {
        return Service::query()->active()->ordered()
            ->where('is_addon_only', false)
            ->where('service_category_id', $this->categoryId)
            ->get();
    }

    #[Computed]
    public function selectedService(): ?Service
    {
        if ($this->serviceId === null) {
            return null;
        }

        return Service::query()->active()
            ->where('is_addon_only', false)
            ->whereIn('service_category_id', $this->categories->modelKeys())
            ->find($this->serviceId);
    }

    /**
     * Extras the selected staff member can add to the selected service.
     *
     * @return Collection<int, Service>
     */
    #[Computed]
    public function addons(): Collection
    {
        if ($this->selectedStaff === null) {
            return new Collection;
        }

        return Service::query()->active()->ordered()
            ->where('available_as_addon', true)
            ->whereIn('service_category_id', $this->selectedStaff->serviceCategories->modelKeys())
            ->when($this->serviceId, fn ($query) => $query->whereKeyNot($this->serviceId))
            ->get();
    }

    /**
     * @return Collection<int, Service>
     */
    #[Computed]
    public function selectedExtras(): Collection
    {
        return $this->addons->whereIn('id', array_map(intval(...), $this->extraIds))->values();
    }

    /**
     * @return list<array{date: CarbonImmutable, working: bool}>
     */
    #[Computed]
    public function days(): array
    {
        return $this->selectedStaff === null ? [] : app(AvailabilityService::class)->bookableDays($this->selectedStaff);
    }

    /**
     * Free start times for the chosen day, as "HH:MM". Only times are computed: no booking details reach the page.
     *
     * @return list<string>
     */
    #[Computed]
    public function availableTimes(): array
    {
        if ($this->selectedStaff === null || $this->selectedService === null || $this->date === null) {
            return [];
        }

        $duration = $this->selectedService->duration_minutes + $this->selectedExtras->sum('duration_minutes');

        return array_map(
            fn (CarbonImmutable $slot): string => $slot->format('H:i'),
            app(AvailabilityService::class)->slots($this->selectedStaff, CarbonImmutable::parse($this->date, config('shop.timezone')), $duration),
        );
    }

    /**
     * The applied promo code, as long as it still passes the checks that don't depend on who is booking.
     */
    #[Computed]
    public function appliedPromo(): ?PromoCode
    {
        return $this->promoCode === null ? null : PromoCode::where('code', $this->promoCode)->first();
    }

    /**
     * Why the applied code doesn't fit the current selection (for example a student code on a Friday), if it doesn't.
     */
    #[Computed]
    public function promoProblem(): ?string
    {
        if ($this->promoCode === null) {
            return null;
        }

        return $this->appliedPromo === null
            ? PromoCodeChecker::NOT_VALID
            : app(PromoCodeChecker::class)->generalRejection($this->appliedPromo, $this->startsAt());
    }

    /**
     * The live price. The every-second-visit discount is deliberately left out here and applied when the
     * booking is confirmed, so typing a mobile number never reveals that person's visit history.
     */
    #[Computed]
    public function quote(): ?Quote
    {
        if ($this->selectedService === null) {
            return null;
        }

        return app(PricingService::class)->quote(
            main: $this->selectedService,
            extras: $this->selectedExtras,
            startsAt: $this->startsAt() ?? ($this->date ? CarbonImmutable::parse($this->date.' 12:00', config('shop.timezone')) : null),
            settings: $this->settings,
            promoCode: $this->promoProblem === null ? $this->appliedPromo : null,
        );
    }

    /**
     * The staff member's regular days off, for their card ("Off Mondays").
     */
    public function daysOffLabel(StaffMember $staffMember): ?string
    {
        $workingDays = $staffMember->workingHours->pluck('day_of_week')->unique();
        $daysOff = collect([1, 2, 3, 4, 5, 6, 0])->reject(fn (int $day): bool => $workingDays->contains($day));

        if ($daysOff->isEmpty() || $daysOff->count() > 3) {
            return null;
        }

        return 'Off '.$daysOff->map(fn (int $day): string => Carbon::getDays()[$day].'s')->join(', ', ' and ');
    }

    private function startsAt(): ?CarbonImmutable
    {
        if ($this->date === null || $this->time === null) {
            return null;
        }

        return CarbonImmutable::createFromFormat('Y-m-d H:i', "{$this->date} {$this->time}", config('shop.timezone'));
    }

    /**
     * Bring every part of the selection back to something valid: a real staff member, a category and
     * service they offer, extras that go with it, a day they work and a time that is still free.
     */
    private function fitSelection(): void
    {
        $this->forgetComputed();

        if ($this->selectedStaff === null) {
            $this->staffId = $this->staffMembers->first()?->id;
            unset($this->selectedStaff, $this->categories);
        }

        if (! $this->categories->contains('id', $this->categoryId)) {
            $this->categoryId = $this->categories->first()?->id;
            unset($this->services);
        }

        if ($this->selectedService === null) {
            $this->serviceId = $this->services->first()?->id;
            unset($this->selectedService, $this->addons);
        }

        $this->extraIds = $this->selectedExtras->modelKeys();

        $workingDates = collect($this->days)->where('working', true)->map(fn (array $day): string => $day['date']->format('Y-m-d'));

        if (! $workingDates->contains($this->date)) {
            $this->date = $workingDates->first();
            $this->time = null;
        }

        if ($this->time !== null && ! in_array($this->time, $this->availableTimes, strict: true)) {
            $this->time = null;
        }

        unset($this->quote, $this->promoProblem);
    }

    private function forgetComputed(): void
    {
        unset(
            $this->staffMembers, $this->selectedStaff, $this->categories, $this->services, $this->selectedService,
            $this->addons, $this->selectedExtras, $this->days, $this->availableTimes, $this->quote, $this->promoProblem,
        );
    }
}

<?php

namespace App\Data;

use App\Enums\BookingSource;
use App\Models\User;
use Carbon\CarbonImmutable;

final readonly class NewBooking
{
    /**
     * @param  list<int>  $extraServiceIds
     * @param  string  $phone  A mobile number already normalised by UkMobile::normalise().
     */
    public function __construct(
        public int $staffMemberId,
        public int $serviceId,
        public array $extraServiceIds,
        public CarbonImmutable $startsAt,
        public string $customerName,
        public string $phone,
        public ?string $email = null,
        public ?string $notes = null,
        public ?string $promoCode = null,
        public BookingSource $source = BookingSource::Online,
        public ?User $createdBy = null,
    ) {}
}

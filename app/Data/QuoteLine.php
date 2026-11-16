<?php

namespace App\Data;

final readonly class QuoteLine
{
    public function __construct(
        public int $serviceId,
        public string $name,
        public int $pricePence,
        public int $durationMinutes,
        public bool $isAddon,
    ) {}
}

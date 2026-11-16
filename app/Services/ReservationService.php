<?php

namespace App\Services;

use App\Exceptions\BookingException;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductReservation;
use App\Models\ShopSetting;
use Illuminate\Support\Facades\DB;

class ReservationService
{
    public const MAX_QUANTITY_PER_PRODUCT = 10;

    /**
     * Reserve products for click and collect. Prices and the free gift are worked out here, from the database.
     *
     * @param  array<int, int>  $quantities  Quantities keyed by product id.
     * @param  string  $phone  A mobile number already normalised by UkMobile::normalise().
     *
     * @throws BookingException When the basket can't be reserved. The message is safe to show.
     */
    public function create(string $customerName, string $phone, array $quantities): ProductReservation
    {
        $quantities = array_filter(array_map(intval(...), $quantities), fn (int $quantity): bool => $quantity > 0);

        if ($quantities === []) {
            throw new BookingException('Your basket is empty. Add products to reserve them.');
        }

        if (max($quantities) > self::MAX_QUANTITY_PER_PRODUCT) {
            throw new BookingException('You can reserve up to '.self::MAX_QUANTITY_PER_PRODUCT.' of each product online.');
        }

        $products = Product::query()->active()->ordered()->findMany(array_keys($quantities));

        if ($products->count() !== count($quantities)) {
            throw new BookingException('One of those products is no longer available. Review your basket and try again.');
        }

        $settings = ShopSetting::current();
        $total = $products->sum(fn (Product $product): int => $product->price_pence * $quantities[$product->id]);

        return DB::transaction(function () use ($customerName, $phone, $quantities, $products, $settings, $total): ProductReservation {
            $customer = Customer::firstOrCreate(['phone' => $phone], ['name' => $customerName]);

            $reservation = ProductReservation::create([
                'reference' => $this->uniqueReference(),
                'customer_id' => $customer->id,
                'customer_name' => $customerName,
                'total_pence' => $total,
                'collect_by' => now(config('shop.timezone'))->addDays($settings->reservation_hold_days)->toDateString(),
            ]);

            foreach ($products as $product) {
                $reservation->items()->create([
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'quantity' => $quantities[$product->id],
                    'unit_price_pence' => $product->price_pence,
                ]);
            }

            $gift = $this->freeGiftFor($total, $settings);

            if ($gift !== null) {
                $reservation->items()->create([
                    'product_id' => $gift->id,
                    'name' => $gift->name,
                    'quantity' => 1,
                    'unit_price_pence' => 0,
                    'is_free_gift' => true,
                ]);
            }

            return $reservation;
        });
    }

    /**
     * The free gift a basket of this value earns, if any.
     */
    public function freeGiftFor(int $totalPence, ShopSetting $settings): ?Product
    {
        if ($settings->free_gift_product_id === null || $totalPence < $settings->free_gift_threshold_pence) {
            return null;
        }

        return Product::query()->active()->find($settings->free_gift_product_id);
    }

    private function uniqueReference(): string
    {
        do {
            $reference = 'PR-'.collect(range(1, 6))
                ->map(fn (): string => 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'[random_int(0, 31)])
                ->implode('');
        } while (ProductReservation::where('reference', $reference)->exists());

        return $reference;
    }
}

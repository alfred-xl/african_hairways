<?php

namespace App\Livewire;

use App\Exceptions\BookingException;
use App\Models\Product;
use App\Models\ShopSetting;
use App\Rules\UkMobileNumber;
use App\Services\ReservationService;
use App\Support\UkMobile;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Click and collect. The basket holds only product ids and quantities; prices, the total
 * and the free gift are always worked out on the server from the database.
 */
#[Title('Hair & beard products')]
class Shop extends Component
{
    /** @var array<int, int> Quantities keyed by product id. */
    public array $cart = [];

    public string $name = '';

    public string $phone = '';

    /** @var array{reference: string, name: string, collectBy: string}|null */
    public ?array $reserved = null;

    public function add(int $productId): void
    {
        $this->reserved = null;

        if ($this->products->contains('id', $productId)) {
            $this->cart[$productId] = min(ReservationService::MAX_QUANTITY_PER_PRODUCT, ($this->cart[$productId] ?? 0) + 1);
        }
    }

    public function remove(int $productId): void
    {
        $quantity = ($this->cart[$productId] ?? 0) - 1;

        if ($quantity > 0) {
            $this->cart[$productId] = $quantity;
        } else {
            unset($this->cart[$productId]);
        }
    }

    public function reserve(ReservationService $reservations): void
    {
        $this->resetErrorBag();

        $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:60'],
            'phone' => ['required', 'string', 'max:25', new UkMobileNumber],
        ], [
            'name.required' => 'Add your name so we know who is collecting.',
            'name.min' => 'Add your name so we know who is collecting.',
            'phone.required' => 'Add your mobile number so we can reach you about your order.',
        ]);

        if (! RateLimiter::attempt('reserve:'.request()->ip(), maxAttempts: 5, callback: fn () => true, decaySeconds: 600)) {
            $this->addError('reserve', 'Too many reservations from this connection. Wait a few minutes, or ask in the shop.');

            return;
        }

        try {
            $reservation = $reservations->create(trim($this->name), UkMobile::normalise($this->phone), $this->quantities());
        } catch (BookingException $exception) {
            $this->addError('reserve', $exception->getMessage());

            return;
        }

        $this->reserved = [
            'reference' => $reservation->reference,
            'name' => $reservation->customer_name,
            'collectBy' => $reservation->collect_by->isoFormat('dddd D MMMM'),
        ];

        $this->cart = [];
    }

    public function render(): View
    {
        return view('livewire.shop');
    }

    #[Computed]
    public function settings(): ShopSetting
    {
        return ShopSetting::current();
    }

    /**
     * @return Collection<int, Product>
     */
    #[Computed]
    public function products(): Collection
    {
        return Product::query()->active()->ordered()->get();
    }

    /**
     * Basket lines priced from the database.
     *
     * @return list<array{product: Product, quantity: int, linePence: int}>
     */
    public function lines(): array
    {
        $quantities = $this->quantities();

        return $this->products
            ->filter(fn (Product $product): bool => isset($quantities[$product->id]))
            ->map(fn (Product $product): array => [
                'product' => $product,
                'quantity' => $quantities[$product->id],
                'linePence' => $product->price_pence * $quantities[$product->id],
            ])
            ->values()
            ->all();
    }

    /**
     * The basket as the browser sent it, reduced to whole quantities between 1 and the per-product limit.
     *
     * @return array<int, int>
     */
    private function quantities(): array
    {
        $quantities = [];

        foreach ($this->cart as $productId => $quantity) {
            $quantity = min(ReservationService::MAX_QUANTITY_PER_PRODUCT, (int) $quantity);

            if ($quantity > 0) {
                $quantities[(int) $productId] = $quantity;
            }
        }

        return $quantities;
    }
}

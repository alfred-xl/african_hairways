<?php

use App\Enums\ReservationStatus;
use App\Livewire\Shop;
use App\Models\Product;
use App\Models\ProductReservation;
use App\Models\ShopSetting;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

it('shows the products that are on sale', function () {
    Product::factory()->create(['name' => 'Beard oil', 'price_pence' => 1200]);
    Product::factory()->inactive()->create(['name' => 'Discontinued wax']);

    $this->get('/shop')
        ->assertOk()
        ->assertSee('Beard oil')
        ->assertSee('£12')
        ->assertDontSee('Discontinued wax');
});

it('reserves a basket at the database prices', function () {
    $oil = Product::factory()->create(['name' => 'Beard oil', 'price_pence' => 1200]);
    $brush = Product::factory()->create(['name' => 'Wave brush', 'price_pence' => 1000]);

    Livewire::test(Shop::class)
        ->call('add', $oil->id)
        ->call('add', $oil->id)
        ->call('add', $brush->id)
        ->call('add', $brush->id)
        ->call('remove', $brush->id)
        ->set('name', 'Tobi A.')
        ->set('phone', '07700 900123')
        ->call('reserve')
        ->assertHasNoErrors()
        ->assertSet('cart', [])
        ->assertSee('Reserved, Tobi A.');

    $reservation = ProductReservation::sole();
    expect($reservation->total_pence)->toBe(3400)
        ->and($reservation->status)->toBe(ReservationStatus::Reserved)
        ->and($reservation->customer->phone)->toBe('+447700900123')
        ->and($reservation->items->pluck('quantity', 'name')->all())->toBe(['Beard oil' => 2, 'Wave brush' => 1]);
});

it('adds the free gift when the basket reaches the threshold', function (int $quantity, bool $getsGift) {
    $durag = Product::factory()->create(['name' => 'Silky durag', 'price_pence' => 600]);
    ShopSetting::factory()->create(['free_gift_threshold_pence' => 2000, 'free_gift_product_id' => $durag->id]);
    $oil = Product::factory()->create(['price_pence' => 1000]);

    Livewire::test(Shop::class)
        ->set('cart', [$oil->id => $quantity])
        ->set('name', 'Tobi')
        ->set('phone', '07700900123')
        ->call('reserve');

    $reservation = ProductReservation::sole();
    expect($reservation->items->where('is_free_gift', true)->count())->toBe($getsGift ? 1 : 0)
        ->and($reservation->total_pence)->toBe($quantity * 1000);
})->with([
    'under the threshold' => [1, false],
    'exactly on the threshold' => [2, true],
    'over the threshold' => [3, true],
]);

it('refuses a basket the browser has tampered with', function (string $tampering) {
    $product = Product::factory()->create();
    $inactive = Product::factory()->inactive()->create();
    $cart = match ($tampering) {
        'inactive product' => [$inactive->id => 1],
        'missing product' => [999999 => 1],
        'negative quantity' => [$product->id => -3],
    };

    Livewire::test(Shop::class)
        ->set('cart', $cart)
        ->set('name', 'Tobi')
        ->set('phone', '07700900123')
        ->call('reserve')
        ->assertHasErrors('reserve');

    expect(ProductReservation::count())->toBe(0);
})->with([
    'a product that is not on sale' => 'inactive product',
    'a product that does not exist' => 'missing product',
    'nothing but negative quantities' => 'negative quantity',
]);

it('caps the quantity of each product', function () {
    $product = Product::factory()->create(['price_pence' => 100]);

    Livewire::test(Shop::class)
        ->set('cart', [$product->id => 500])
        ->set('name', 'Tobi')
        ->set('phone', '07700900123')
        ->call('reserve');

    expect(ProductReservation::sole()->items->first()->quantity)->toBe(10);
});

it('asks for a name and a UK mobile number before reserving', function () {
    $product = Product::factory()->create();

    Livewire::test(Shop::class)
        ->call('add', $product->id)
        ->set('name', '')
        ->set('phone', '12345')
        ->call('reserve')
        ->assertHasErrors(['name', 'phone']);

    expect(ProductReservation::count())->toBe(0);
});

it('stops taking reservations from one connection after five in ten minutes', function () {
    $product = Product::factory()->create();
    foreach (range(1, 5) as $attempt) {
        RateLimiter::hit('reserve:127.0.0.1', 600);
    }

    Livewire::test(Shop::class)
        ->call('add', $product->id)
        ->set('name', 'Tobi')
        ->set('phone', '07700900123')
        ->call('reserve')
        ->assertHasErrors('reserve');

    expect(ProductReservation::count())->toBe(0);
});

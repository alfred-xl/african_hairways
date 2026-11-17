<?php

use App\Models\User;

it('sends guests to the staff sign-in page', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('lets staff work the diary, blocked time and reservations', function (string $path) {
    openShop();

    $this->actingAs(User::factory()->create())->get($path)->assertOk();
})->with([
    'diary' => '/admin',
    'blocked time' => '/admin/blocked-times',
    'product reservations' => '/admin/product-reservations',
]);

it('forbids staff from the owner-only screens', function (string $path) {
    openShop();

    $this->actingAs(User::factory()->create())->get($path)->assertForbidden();
})->with('owner screens');

it('lets the owner open every owner-only screen', function (string $path) {
    openShop();

    $this->actingAs(User::factory()->owner()->create())->get($path)->assertOk();
})->with('owner screens');

it('shows staff only the menu items they can use', function () {
    openShop();

    $this->actingAs(User::factory()->create())->get('/admin')
        ->assertSee('Blocked time')
        ->assertDontSee('Promo codes')
        ->assertDontSee('Staff logins');
});

dataset('owner screens', [
    'barbers and stylists' => '/admin/staff-members',
    'services and prices' => '/admin/services',
    'service categories' => '/admin/service-categories',
    'opening hours' => '/admin/opening-hours',
    'products' => '/admin/products',
    'promo codes' => '/admin/promo-codes',
    'shop settings' => '/admin/shop-settings',
    'staff logins' => '/admin/users',
]);

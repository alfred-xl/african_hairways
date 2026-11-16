<?php

use App\Http\Controllers\BookingController;
use App\Http\Controllers\OffersController;
use App\Http\Controllers\PriceListController;
use App\Livewire\BookAppointment;
use App\Livewire\Shop;
use Illuminate\Support\Facades\Route;

Route::livewire('/', BookAppointment::class)->name('book');
Route::get('/prices', PriceListController::class)->name('prices');
Route::livewire('/shop', Shop::class)->name('shop');
Route::get('/offers', OffersController::class)->name('offers');

// A booking's private link. The long random token is the only thing that grants access.
Route::middleware('throttle:30,1')->group(function () {
    Route::get('/booking/{booking:access_token}', [BookingController::class, 'show'])->name('bookings.show');
    Route::post('/booking/{booking:access_token}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');
});

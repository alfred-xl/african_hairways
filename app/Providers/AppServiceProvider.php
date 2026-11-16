<?php

namespace App\Providers;

use App\Models\OpeningHour;
use App\Models\ShopSetting;
use App\Models\User;
use App\Support\OpeningHoursSummary;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Owner-only parts of the back office: staff, services, prices, opening hours, products and promo codes.
        Gate::define('manage-shop', fn (User $user): bool => $user->isOwner());

        View::composer('components.layouts.app', function (ViewContract $view): void {
            $view->with('shop', ShopSetting::current());
            $view->with('openingHoursLine', OpeningHoursSummary::line(OpeningHour::all()));
        });
    }
}

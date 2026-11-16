<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\PromoCode;
use App\Models\Service;
use App\Models\ShopSetting;
use App\Support\Money;
use Illuminate\Contracts\View\View;

class OffersController extends Controller
{
    /**
     * Show the current offers: promo codes, automatic discounts, featured services and the free gift.
     */
    public function __invoke(): View
    {
        $settings = ShopSetting::current();

        return view('pages.offers', ['offers' => [
            ...$this->promoCodeOffers(),
            ...$this->automaticOffers($settings),
            ...$this->featuredServiceOffers(),
            ...$this->freeGiftOffers($settings),
        ]]);
    }

    /**
     * @return list<array{heading: string, code?: string, text: string}>
     */
    private function promoCodeOffers(): array
    {
        return PromoCode::query()
            ->where('is_active', true)
            ->where('show_on_offers_page', true)
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->orderBy('id')
            ->get()
            ->map(fn (PromoCode $promoCode): array => [
                'heading' => $promoCode->label,
                'code' => $promoCode->code,
                'text' => $promoCode->description ?? $promoCode->note ?? '',
            ])
            ->all();
    }

    /**
     * @return list<array{heading: string, text: string}>
     */
    private function automaticOffers(ShopSetting $settings): array
    {
        $offers = [];

        if ($settings->second_visit_percent > 0) {
            $offers[] = [
                'heading' => "{$settings->second_visit_percent}% off every 2nd visit",
                'text' => "Book with the same mobile number and every second appointment is {$settings->second_visit_percent}% off, applied automatically when you confirm. Can't be combined with promo codes.",
            ];
        }

        if ($settings->kids_midweek_discount_pence > 0 && Service::query()->active()->where('is_kids', true)->exists()) {
            $offers[] = [
                'heading' => 'Kids midweek '.Money::format($settings->kids_midweek_discount_pence).' off',
                'text' => 'Off kids cuts on Tuesdays, Wednesdays and Thursdays. Applied automatically.',
            ];
        }

        return $offers;
    }

    /**
     * @return list<array{heading: string, text: string}>
     */
    private function featuredServiceOffers(): array
    {
        return Service::query()->active()->ordered()->whereNotNull('badge')->get()
            ->map(fn (Service $service): array => [
                'heading' => $service->name.' '.Money::format($service->price_pence),
                'text' => collect([$service->badge, $service->description])->filter()->join(': ').'.',
            ])
            ->all();
    }

    /**
     * @return list<array{heading: string, text: string}>
     */
    private function freeGiftOffers(ShopSetting $settings): array
    {
        $gift = $settings->free_gift_product_id ? Product::query()->active()->find($settings->free_gift_product_id) : null;

        if ($gift === null) {
            return [];
        }

        return [[
            'heading' => 'Free '.mb_strtolower($gift->name),
            'text' => 'Spend '.Money::format($settings->free_gift_threshold_pence).' or more on products and get a '.mb_strtolower($gift->name).' free.',
        ]];
    }
}

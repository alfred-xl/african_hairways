# African Hairways booking

Online booking, price list, click-and-collect shop and staff back office for African Hairways
(part of Touch2finish Services Ltd, London).

Built with Laravel 13, Livewire 4, Filament 5, Tailwind 4 and Pest.

## Getting started

```bash
composer install
npm install
cp .env.example .env        # skip if you already have a .env
php artisan key:generate    # skip if APP_KEY is already set
php artisan migrate --seed
npm run build
php artisan serve
```

Then open:

- <http://localhost:8000> for the customer site (booking, prices, shop, offers)
- <http://localhost:8000/admin> for the staff back office

`composer run dev` runs the server, queue worker, log viewer and Vite together while you are developing.

### Database

`.env.example` uses SQLite, which needs no setup. To use MySQL or MariaDB instead, set
`DB_CONNECTION=mysql` and the `DB_*` values in `.env`, create the database, then run
`php artisan migrate --seed`. The test suite passes on both.

### Seeded logins

The seeder creates two logins for local use. **Change or delete them before going live.**

| Role  | Email                        | Password   | Can do |
|-------|------------------------------|------------|--------|
| Owner | `owner@africanhairways.test` | `password` | Everything |
| Staff | `dami@africanhairways.test`  | `password` | Diary, blocked time, product reservations |

The seeder also loads the staff, services, prices, products, opening hours and promo codes
from the original prototype, so the site is usable straight away. It is safe to run again:
it updates those records rather than duplicating them.

## What is where

| Area | Location |
|------|----------|
| Booking rules (availability, pricing, promo codes, creating bookings) | `app/Services` |
| Customer pages | `app/Livewire`, `app/Http/Controllers`, `resources/views` |
| Staff back office | `app/Filament` |
| Shop details that rarely change (timezone, parent company) | `config/shop.php` |
| Everything the owner can change (hours, prices, discounts, booking window) | The back office, under "Shop setup" |
| Colours and fonts | `resources/css/app.css` |

## How the rules are enforced

All of these are decided on the server; the browser only ever sends ids, a date, a time and the
customer's own details.

- **No double bookings.** `BookingService::create()` locks the staff member's row inside a database
  transaction, re-checks the time, then saves. Two people confirming the same time are handled one
  after the other, and the second is refused. On SQLite, transactions take the write lock up front
  (`transaction_mode` in `config/database.php`) to give the same guarantee.
- **Available times** come from the shop's opening hours, the staff member's working pattern,
  blocked time, existing bookings, the length of the service plus extras, the slot step, the booking
  window and the minimum notice. See `AvailabilityService`.
- **Prices and discounts** are worked out from the database by `PricingService`. Money is stored
  as whole pence.
- **Discounts**, in order: kids' midweek amount off (Tuesday to Thursday), then either 20% off
  every second completed visit or a promo code, never both.
- **Visit history stays private.** The second-visit discount and first-visit-only checks run only
  when a booking is confirmed, so typing someone's mobile number shows nothing about them.
- **Customer details** (names, numbers, notes) are only shown inside the signed-in back office and
  on a booking's own private link.
- **Times** are stored in UTC and shown in Europe/London.

## Tests

```bash
php artisan test
```

## Before going live

- Replace the seeded logins and set real shop details under "Shop setup > Shop settings".
- Set `APP_ENV=production`, `APP_DEBUG=false` and `APP_URL` in `.env`.
- Configure a real mail service (`MAIL_*`). Confirmation emails are written to
  `storage/logs/laravel.log` until then.
- Serve the site over HTTPS: booking links are private and should never travel unencrypted.

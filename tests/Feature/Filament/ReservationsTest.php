<?php

use App\Enums\ReservationStatus;
use App\Filament\Resources\BlockedTimes\Pages\ManageBlockedTimes;
use App\Filament\Resources\ProductReservations\Pages\ManageProductReservations;
use App\Models\BlockedTime;
use App\Models\ProductReservation;
use App\Models\User;
use App\Services\AvailabilityService;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

it('lists reservations waiting for collection', function () {
    $waiting = ProductReservation::factory()->create(['customer_name' => 'Waiting Wale']);
    $collected = ProductReservation::factory()->collected()->create(['customer_name' => 'Collected Chidi']);
    actingAs(User::factory()->create());

    Livewire::test(ManageProductReservations::class)
        ->assertCanSeeTableRecords([$waiting])
        ->assertCanNotSeeTableRecords([$collected]);
});

it('marks a reservation as collected', function () {
    $reservation = ProductReservation::factory()->create();
    actingAs(User::factory()->create());

    Livewire::test(ManageProductReservations::class)
        ->callAction(TestAction::make('collect')->table($reservation));

    $reservation->refresh();
    expect($reservation->status)->toBe(ReservationStatus::Collected)
        ->and($reservation->collected_at)->not->toBeNull();
});

it('lets staff block out time, stored in UTC and credited to them', function () {
    openShop();
    $staffMember = staffMember();
    $user = User::factory()->create();
    actingAs($user);

    Livewire::test(ManageBlockedTimes::class)
        ->callAction('create', [
            'staff_member_id' => $staffMember->id,
            'type' => 'holiday',
            'starts_at' => '2026-06-08 09:00',
            'ends_at' => '2026-06-12 19:00',
            'reason' => 'Family trip',
        ])
        ->assertHasNoFormErrors();

    $block = BlockedTime::sole();
    expect($block->starts_at->format('Y-m-d H:i'))->toBe('2026-06-08 08:00') // 09:00 London is 08:00 UTC in June
        ->and($block->created_by_user_id)->toBe($user->id)
        ->and(slotTimesOn($staffMember, '2026-06-09'))->toBe([]);
});

function slotTimesOn($staffMember, string $date): array
{
    return app(AvailabilityService::class)->slots($staffMember, london($date), 30);
}

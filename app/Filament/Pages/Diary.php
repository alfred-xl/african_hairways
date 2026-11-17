<?php

namespace App\Filament\Pages;

use App\Data\NewBooking;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Exceptions\BookingException;
use App\Filament\Resources\BlockedTimes\BlockedTimeResource;
use App\Models\BlockedTime;
use App\Models\Booking;
use App\Models\Service;
use App\Models\StaffMember;
use App\Rules\UkMobileNumber;
use App\Services\BookingService;
use App\Support\UkMobile;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

/**
 * The day diary: each staff member's bookings and blocked time for one day, with the day's expected takings.
 * Open to every signed-in member of staff.
 */
class Diary extends Page
{
    protected string $view = 'filament.pages.diary';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?int $navigationSort = 1;

    /** The day on show, as Y-m-d in the shop's timezone. */
    #[Url]
    public ?string $date = null;

    public static function getRoutePath(Panel $panel): string
    {
        return '/';
    }

    public function mount(): void
    {
        $this->date = $this->day()->format('Y-m-d');
    }

    public function getTitle(): string
    {
        return 'Diary: '.$this->day()->isoFormat('dddd D MMMM');
    }

    public function previousDay(): void
    {
        $this->date = $this->day()->subDay()->format('Y-m-d');
    }

    public function nextDay(): void
    {
        $this->date = $this->day()->addDay()->format('Y-m-d');
    }

    public function today(): void
    {
        $this->date = CarbonImmutable::now(config('shop.timezone'))->format('Y-m-d');
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->addBookingAction(),
            Action::make('blockTime')
                ->label('Block out time')
                ->color('gray')
                ->icon(Heroicon::OutlinedNoSymbol)
                ->url(BlockedTimeResource::getUrl()),
        ];
    }

    /**
     * Add a walk-in or phone booking by hand. It goes through the same service as online bookings,
     * so prices come from the database and it can never overlap another booking.
     */
    protected function addBookingAction(): Action
    {
        return Action::make('addBooking')
            ->label('Add booking')
            ->icon(Heroicon::OutlinedPlus)
            ->modalHeading('Add a booking')
            ->modalSubmitActionLabel('Add booking')
            ->schema([
                Select::make('staff_member_id')
                    ->label('With')
                    ->options(fn (): array => StaffMember::query()->active()->ordered()->pluck('name', 'id')->all())
                    ->default(fn (): ?int => auth()->user()->staff_member_id)
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (callable $set): void {
                        $set('service_id', null);
                        $set('extra_service_ids', []);
                    }),
                Select::make('service_id')
                    ->label('Service')
                    ->options(fn (Get $get): array => $this->serviceOptions($get('staff_member_id'), extras: false))
                    ->required()
                    ->searchable()
                    ->live(),
                Select::make('extra_service_ids')
                    ->label('Extras')
                    ->multiple()
                    ->options(fn (Get $get): array => $this->serviceOptions($get('staff_member_id'), extras: true, except: $get('service_id'))),
                DateTimePicker::make('starts_at')
                    ->label('Starts')
                    ->timezone(config('shop.timezone'))
                    ->seconds(false)
                    ->default(fn (): CarbonImmutable => $this->defaultStart())
                    ->required(),
                Select::make('source')
                    ->label('How did they book?')
                    ->options([
                        BookingSource::WalkIn->value => BookingSource::WalkIn->label(),
                        BookingSource::Phone->value => BookingSource::Phone->label(),
                    ])
                    ->default(BookingSource::WalkIn->value)
                    ->required()
                    ->helperText('Walk-ins are charged the walk-in price where a service has one.'),
                TextInput::make('customer_name')->label('Customer name')->required()->minLength(2)->maxLength(60),
                TextInput::make('phone')->label('Mobile number')->tel()->required()->maxLength(25)->rule(new UkMobileNumber)
                    ->helperText('Counts towards their every-second-visit discount.'),
                TextInput::make('email')->label('Email (optional)')->email()->maxLength(254),
                TextInput::make('promo_code')->label('Promo code (optional)')->maxLength(30),
                Textarea::make('notes')->rows(2)->maxLength(300)->columnSpanFull(),
            ])
            ->action(function (array $data, Action $action, BookingService $bookings): void {
                try {
                    $booking = $bookings->create(new NewBooking(
                        staffMemberId: (int) $data['staff_member_id'],
                        serviceId: (int) $data['service_id'],
                        extraServiceIds: array_map(intval(...), $data['extra_service_ids'] ?? []),
                        startsAt: CarbonImmutable::parse($data['starts_at'], config('app.timezone')),
                        customerName: trim($data['customer_name']),
                        phone: UkMobile::normalise($data['phone']),
                        email: filled($data['email'] ?? null) ? trim($data['email']) : null,
                        notes: filled($data['notes'] ?? null) ? trim($data['notes']) : null,
                        promoCode: filled($data['promo_code'] ?? null) ? $data['promo_code'] : null,
                        source: BookingSource::from($data['source']),
                        createdBy: auth()->user(),
                    ));
                } catch (BookingException $exception) {
                    Notification::make()->danger()->title("Booking wasn't added")->body($exception->getMessage())->send();

                    $action->halt();
                }

                $this->date = $booking->localStartsAt()->format('Y-m-d');

                Notification::make()->success()
                    ->title('Booking added')
                    ->body("{$booking->customer_name}, {$booking->localStartsAt()->isoFormat('ddd D MMM [at] HH:mm')}.")
                    ->send();
            });
    }

    public function completeAction(): Action
    {
        return Action::make('complete')
            ->label('Mark done')
            ->size('sm')
            ->action(fn (array $arguments, BookingService $bookings) => $this->changeBooking(
                $arguments, fn (Booking $booking) => $bookings->complete($booking), 'Marked as done',
            ));
    }

    public function noShowAction(): Action
    {
        return Action::make('noShow')
            ->label('No-show')
            ->size('sm')
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading('Mark as a no-show?')
            ->modalDescription("The customer didn't turn up. The booking is left out of takings and doesn't count as a visit.")
            ->action(fn (array $arguments, BookingService $bookings) => $this->changeBooking(
                $arguments, fn (Booking $booking) => $bookings->markNoShow($booking), 'Marked as a no-show',
            ));
    }

    public function cancelAction(): Action
    {
        return Action::make('cancel')
            ->label('Cancel')
            ->size('sm')
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading('Cancel this booking?')
            ->modalDescription('The time becomes free for someone else to book.')
            ->modalSubmitActionLabel('Cancel booking')
            ->modalCancelActionLabel('Keep it')
            ->action(fn (array $arguments, BookingService $bookings) => $this->changeBooking(
                $arguments, fn (Booking $booking) => $bookings->cancel($booking, 'staff'), 'Booking cancelled',
            ));
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $dayStart = $this->day();
        $dayEnd = $dayStart->addDay();

        $bookings = Booking::query()
            ->overlapping($dayStart, $dayEnd)
            ->where('starts_at', '>=', $dayStart->utc())
            ->with(['items', 'discounts', 'customer', 'promoCode'])
            ->orderBy('starts_at')
            ->get();

        $blocks = BlockedTime::query()->overlapping($dayStart, $dayEnd)->orderBy('starts_at')->get();

        // Staff who are switched off still appear on days they have bookings, so nothing is hidden.
        $staffMembers = StaffMember::query()->ordered()
            ->where(fn (Builder $query) => $query->where('is_active', true)->orWhereIn('id', $bookings->pluck('staff_member_id')))
            ->with(['workingHours' => fn ($query) => $query->where('day_of_week', $dayStart->dayOfWeek)])
            ->get();

        $live = $bookings->whereIn('status', BookingStatus::occupyingTime());

        return [
            'day' => $dayStart,
            'isToday' => $dayStart->isSameDay(CarbonImmutable::now(config('shop.timezone'))),
            'columns' => $staffMembers->map(fn (StaffMember $staffMember): array => [
                'staffMember' => $staffMember,
                'isWorking' => $staffMember->workingHours->isNotEmpty(),
                'bookings' => $bookings->where('staff_member_id', $staffMember->id)->values(),
                'blocks' => $blocks->filter(fn (BlockedTime $block): bool => $block->staff_member_id === null || $block->staff_member_id === $staffMember->id)->values(),
            ]),
            'stats' => [
                'appointments' => $live->count(),
                'completed' => $bookings->where('status', BookingStatus::Completed)->count(),
                // Chair renters are paid directly by their customers, so their bookings aren't shop takings.
                'takingsPence' => $live->where('pay_staff_directly', false)->sum('total_pence'),
            ],
        ];
    }

    /**
     * The day on show. Anything that isn't a real date falls back to today.
     */
    private function day(): CarbonImmutable
    {
        $timezone = config('shop.timezone');

        if (is_string($this->date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->date) === 1) {
            $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $this->date, $timezone);

            if ($parsed !== false && $parsed->format('Y-m-d') === $this->date) {
                return $parsed;
            }
        }

        return CarbonImmutable::now($timezone)->startOfDay();
    }

    /**
     * A sensible start for a hand-made booking: the next quarter hour today, or 9am on another day.
     */
    private function defaultStart(): CarbonImmutable
    {
        $now = CarbonImmutable::now(config('shop.timezone'));

        return $this->day()->isSameDay($now)
            ? $now->setTime($now->hour, intdiv($now->minute, 15) * 15)->addMinutes(15)
            : $this->day()->setTime(9, 0);
    }

    /**
     * @return array<int, string>
     */
    private function serviceOptions(mixed $staffMemberId, bool $extras, mixed $except = null): array
    {
        $staffMember = StaffMember::with('serviceCategories')->find($staffMemberId);

        if ($staffMember === null) {
            return [];
        }

        return Service::query()->active()->ordered()
            ->whereIn('service_category_id', $staffMember->serviceCategories->modelKeys())
            ->when($extras, fn (Builder $query) => $query->where('available_as_addon', true), fn (Builder $query) => $query->where('is_addon_only', false))
            ->when($except, fn (Builder $query) => $query->whereKeyNot($except))
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  callable(Booking): void  $change
     */
    private function changeBooking(array $arguments, callable $change, string $successTitle): void
    {
        $booking = Booking::findOrFail($arguments['booking'] ?? null);

        try {
            $change($booking);
        } catch (BookingException $exception) {
            Notification::make()->warning()->title($exception->getMessage())->send();

            return;
        }

        Notification::make()->success()->title($successTitle)->send();
    }
}

@php
    use App\Support\Money;

    $staff = $this->selectedStaff;
    $quote = $this->quote;
    $ready = $this->time !== null;
@endphp

<div>
    <h2>Book an appointment</h2>
    <p class="lead">Pick your barber or stylist, choose your service and any extras, then grab a time. Pay in the shop after your appointment.</p>

    <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
        <div class="grid min-w-0 gap-5">
            {{-- Step 1: who --}}
            <section class="card">
                <x-step-heading number="1">Choose your barber or stylist</x-step-heading>

                <div class="grid grid-cols-[repeat(auto-fit,minmax(200px,1fr))] gap-3">
                    @foreach ($this->staffMembers as $staffMember)
                        <button
                            type="button"
                            wire:key="staff-{{ $staffMember->id }}"
                            wire:click="selectStaff({{ $staffMember->id }})"
                            aria-pressed="{{ $staffMember->id === $this->staffId ? 'true' : 'false' }}"
                            class="grid gap-1.5 rounded-xl border-2 border-line bg-page p-3.5 text-left aria-pressed:border-accent aria-pressed:bg-soft"
                        >
                            <span class="grid size-11 place-items-center rounded-full font-display text-xl text-white" style="background: {{ $staffMember->color }}">{{ mb_substr($staffMember->name, 0, 1) }}</span>
                            <strong class="text-[1.05rem]">{{ $staffMember->name }}</strong>
                            <span class="text-sm text-muted">{{ collect([$staffMember->job_title, $this->daysOffLabel($staffMember)])->filter()->join(' · ') }}</span>
                            <span class="flex flex-wrap gap-1">
                                @foreach ($staffMember->tags ?? [] as $tag)
                                    <span class="tag">{{ $tag }}</span>
                                @endforeach
                            </span>
                        </button>
                    @endforeach
                </div>
            </section>

            {{-- Step 2: what --}}
            <section class="card">
                <x-step-heading number="2">Choose your service</x-step-heading>

                <div class="mb-3 flex flex-wrap gap-1.5">
                    @foreach ($this->categories as $category)
                        <button
                            type="button"
                            wire:key="category-{{ $category->id }}"
                            wire:click="selectCategory({{ $category->id }})"
                            aria-pressed="{{ $category->id === $this->categoryId ? 'true' : 'false' }}"
                            class="chip"
                        >{{ $category->name }}</button>
                    @endforeach
                </div>

                <div class="grid gap-2">
                    @foreach ($this->services as $service)
                        <label class="option" wire:key="service-{{ $service->id }}">
                            <input type="radio" name="service" value="{{ $service->id }}" wire:model.live="serviceId">
                            <span>
                                <span class="block">{{ $service->name }}@if ($service->badge)<span class="badge">{{ $service->badge }}</span>@endif</span>
                                <span class="block text-sm text-muted">{{ collect([$service->description, $service->duration_minutes.' min'])->filter()->join(' · ') }}</span>
                            </span>
                            <span class="font-bold tabular-nums">{{ Money::format($service->price_pence) }}</span>
                        </label>
                    @endforeach
                </div>

                @if ($this->addons->isNotEmpty())
                    <p class="mt-4 mb-2 text-[0.95rem] font-bold">Add extras</p>

                    <div class="grid gap-2">
                        @foreach ($this->addons as $addon)
                            <label class="option" wire:key="addon-{{ $addon->id }}">
                                <input type="checkbox" value="{{ $addon->id }}" wire:model.live="extraIds">
                                <span>
                                    <span class="block">{{ $addon->name }}</span>
                                    <span class="block text-sm text-muted">{{ collect([$addon->description, '+'.$addon->duration_minutes.' min'])->filter()->join(' · ') }}</span>
                                </span>
                                <span class="font-bold tabular-nums">+{{ Money::format($addon->price_pence) }}</span>
                            </label>
                        @endforeach
                    </div>
                @endif
            </section>

            {{-- Step 3: when --}}
            <section class="card">
                <x-step-heading number="3">Pick a date and time</x-step-heading>

                <div class="flex gap-2 overflow-x-auto pb-1.5">
                    @foreach ($this->days as $day)
                        @php($value = $day['date']->format('Y-m-d'))
                        <button
                            type="button"
                            wire:key="day-{{ $value }}"
                            wire:click="selectDate('{{ $value }}')"
                            aria-pressed="{{ $value === $this->date ? 'true' : 'false' }}"
                            aria-label="{{ $day['date']->isoFormat('dddd D MMMM') }}{{ $day['working'] ? '' : ', unavailable' }}"
                            @disabled(! $day['working'])
                            class="group min-w-16 shrink-0 rounded-[10px] border border-line bg-page px-1.5 py-2 text-center disabled:cursor-not-allowed disabled:opacity-40 aria-pressed:border-ink aria-pressed:bg-ink aria-pressed:text-page"
                        >
                            <small class="block text-xs text-muted group-aria-pressed:text-page">{{ $day['date']->isoFormat('ddd') }}</small>
                            <b class="block font-display text-[1.3rem] tabular-nums">{{ $day['date']->day }}</b>
                            <small class="block text-xs text-muted group-aria-pressed:text-page">{{ $day['working'] ? $day['date']->isoFormat('MMM') : 'Off' }}</small>
                        </button>
                    @endforeach
                </div>

                <div class="mt-3.5" wire:loading.class="opacity-50" wire:target="selectDate, selectStaff, serviceId, extraIds">
                    @if ($this->date === null)
                        <p class="py-2 text-[0.92rem] text-muted">{{ $staff?->name ?? 'This person' }} has no days available in the next {{ $this->settings->booking_window_days }} days. Try another barber or stylist.</p>
                    @elseif ($this->availableTimes === [])
                        <p class="py-2 text-[0.92rem] text-muted">Fully booked for a {{ $quote?->durationMinutes }}-minute appointment. Try another day or barber.</p>
                    @else
                        <div class="grid grid-cols-[repeat(auto-fill,minmax(76px,1fr))] gap-2">
                            @foreach ($this->availableTimes as $slot)
                                <button
                                    type="button"
                                    wire:key="slot-{{ $this->date }}-{{ $slot }}"
                                    wire:click="selectTime('{{ $slot }}')"
                                    aria-pressed="{{ $slot === $this->time ? 'true' : 'false' }}"
                                    class="rounded-lg border border-line bg-page py-2 font-semibold tabular-nums aria-pressed:border-accent aria-pressed:bg-accent aria-pressed:text-accent-ink"
                                >{{ $slot }}</button>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>

            {{-- Step 4: who's coming --}}
            <section class="card">
                <x-step-heading number="4">Your details</x-step-heading>

                <div class="grid gap-3">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="field">
                            Name
                            <input type="text" wire:model="name" autocomplete="name" maxlength="60" placeholder="e.g. Tobi A.">
                            @error('name') <span class="field-error">{{ $message }}</span> @enderror
                        </label>

                        <label class="field">
                            Mobile number
                            <input type="tel" wire:model="phone" autocomplete="tel" inputmode="tel" maxlength="25" placeholder="07…">
                            @error('phone') <span class="field-error">{{ $message }}</span> @enderror
                        </label>
                    </div>

                    @if ($this->settings->second_visit_percent > 0)
                        <p class="text-sm text-muted">Book with the same mobile number each time: every second visit is {{ $this->settings->second_visit_percent }}% off, taken off when you confirm.</p>
                    @endif

                    <label class="field">
                        Email (optional)
                        <input type="email" wire:model="email" autocomplete="email" inputmode="email" maxlength="254" placeholder="For your confirmation and a link to change your booking">
                        @error('email') <span class="field-error">{{ $message }}</span> @enderror
                    </label>

                    <label class="field">
                        Notes for your barber (optional)
                        <textarea wire:model="notes" rows="2" maxlength="300" placeholder="e.g. skin fade, keep a little length on top"></textarea>
                        @error('notes') <span class="field-error">{{ $message }}</span> @enderror
                    </label>

                    <div class="field">
                        <label for="promo">Promo code</label>
                        <div class="flex gap-2">
                            <input type="text" id="promo" wire:model="promoInput" wire:keydown.enter.prevent="applyPromo" class="flex-1 uppercase placeholder:normal-case" maxlength="30" autocapitalize="characters" autocomplete="off" placeholder="Got a code?">
                            @if ($this->promoCode)
                                <button type="button" class="btn btn-ghost" wire:click="removePromo">Remove</button>
                            @else
                                <button type="button" class="btn btn-ghost" wire:click="applyPromo">Apply</button>
                            @endif
                        </div>
                        <div aria-live="polite">
                            @error('promoInput')
                                <span class="field-error">{{ $message }}</span>
                            @else
                                @if ($this->promoProblem)
                                    <span class="field-error">{{ $this->promoProblem }}</span>
                                @elseif ($this->appliedPromo)
                                    <span class="text-sm font-normal text-good">{{ $this->appliedPromo->label }} applied.@if ($this->appliedPromo->note) {{ $this->appliedPromo->note }}.@endif</span>
                                @endif
                            @enderror
                        </div>
                    </div>
                </div>
            </section>
        </div>

        {{-- Live summary --}}
        <aside class="card lg:sticky lg:top-24" aria-live="polite">
            <h3>Your booking</h3>

            @if ($quote && $staff)
                <dl class="summary-list">
                    <dt>With</dt>
                    <dd>{{ $staff->name }}</dd>

                    <dt>When</dt>
                    <dd>
                        @if ($this->date)
                            {{ \Carbon\CarbonImmutable::parse($this->date)->isoFormat('ddd D MMM') }}@if ($ready) · {{ $this->time }}@endif
                        @else
                            —
                        @endif
                    </dd>

                    @foreach ($quote->lines as $line)
                        <dt>{{ $line->isAddon ? '+ ' : '' }}{{ $line->name }}</dt>
                        <dd>{{ Money::format($line->pricePence) }}</dd>
                    @endforeach

                    @foreach ($quote->discounts as $discount)
                        <dt class="!text-good">{{ $discount->label }}</dt>
                        <dd class="text-good">−{{ Money::format($discount->amountPence) }}</dd>
                    @endforeach

                    <dt>Time in the chair</dt>
                    <dd>{{ $quote->durationMinutes }} min</dd>
                </dl>

                <div class="total">
                    <span>Total</span>
                    <b>{{ Money::format($quote->totalPence) }}</b>
                </div>

                <p class="mt-2 text-sm text-muted">
                    {{ $staff->is_chair_renter ? "Pay {$staff->name} directly on the day." : 'Pay in the shop by card or cash.' }}
                </p>

                <button type="button" class="btn mt-3 w-full" wire:click="confirm" wire:loading.attr="disabled" wire:target="confirm" @disabled(! $ready)>
                    <span wire:loading.remove wire:target="confirm">{{ $ready ? 'Confirm booking' : 'Pick a time to continue' }}</span>
                    <span wire:loading wire:target="confirm">Booking…</span>
                </button>

                <div aria-live="assertive">
                    @error('booking')
                        <p class="mt-2 text-sm text-accent">{{ $message }}</p>
                    @enderror

                    @if ($errors->hasAny(['name', 'phone', 'email', 'notes']))
                        <p class="mt-2 text-sm text-accent">Check your details in step 4.</p>
                    @endif
                </div>
            @else
                <p class="py-2 text-[0.92rem] text-muted">Online booking isn't available right now. Call us on {{ $this->settings->phone }}.</p>
            @endif
        </aside>
    </div>
</div>

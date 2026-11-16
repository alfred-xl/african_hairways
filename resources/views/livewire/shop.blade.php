@php
    use App\Support\Money;

    $lines = $this->lines();
    $total = array_sum(array_column($lines, 'linePence'));
    $threshold = $this->settings->free_gift_threshold_pence;
    $gift = app(\App\Services\ReservationService::class)->freeGiftFor(PHP_INT_MAX, $this->settings);
    $earnsGift = $gift !== null && $total >= $threshold;
@endphp

<div>
    <h2>Hair &amp; beard products</h2>
    <p class="lead">
        Reserve products online and collect them in the shop.
        @if ($gift) Spend {{ Money::format($threshold) }} or more and we add a free {{ mb_strtolower($gift->name) }}. @endif
    </p>

    <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
        <div class="grid min-w-0 grid-cols-2 gap-3 sm:grid-cols-[repeat(auto-fill,minmax(180px,1fr))]">
            @foreach ($this->products as $product)
                <div class="grid content-start gap-2 rounded-xl border border-line bg-surface p-3.5" wire:key="product-{{ $product->id }}">
                    <div class="grid h-[70px] place-items-center rounded-lg font-display text-lg tracking-wider text-white" style="background: {{ $product->swatch_color }}">{{ mb_strtoupper(explode(' ', $product->name)[0]) }}</div>
                    <strong>{{ $product->name }}</strong>
                    <p class="text-sm text-muted">{{ $product->description }}</p>
                    <div class="flex items-center justify-between">
                        <b class="tabular-nums">{{ Money::format($product->price_pence) }}</b>
                        <button type="button" class="btn btn-small" wire:click="add({{ $product->id }})" aria-label="Add {{ $product->name }} to your basket">Add</button>
                    </div>
                </div>
            @endforeach
        </div>

        <aside class="card lg:sticky lg:top-24" aria-live="polite">
            <h3>Your basket</h3>

            @if ($this->reserved)
                <div class="mt-3 rounded-[10px] bg-soft p-3" role="status">
                    <p><b>Reserved, {{ $this->reserved['name'] }}.</b> Collect and pay in the shop by {{ $this->reserved['collectBy'] }}.</p>
                    <p class="mt-1 text-sm text-muted">Your reference is <b class="text-ink">{{ $this->reserved['reference'] }}</b>. Give it, or your name, at the counter.</p>
                </div>
            @endif

            @forelse ($lines as $line)
                <div class="flex items-center justify-between gap-2 border-b border-dashed border-line py-1.5" wire:key="line-{{ $line['product']->id }}">
                    <span class="min-w-0 flex-1">{{ $line['product']->name }}</span>
                    <span class="flex items-center gap-1.5">
                        <button type="button" class="size-8 rounded-md border border-line bg-page" wire:click="remove({{ $line['product']->id }})" aria-label="Remove one {{ $line['product']->name }}">−</button>
                        <span class="w-5 text-center tabular-nums">{{ $line['quantity'] }}</span>
                        <button type="button" class="size-8 rounded-md border border-line bg-page" wire:click="add({{ $line['product']->id }})" aria-label="Add one {{ $line['product']->name }}">+</button>
                    </span>
                    <span class="w-14 text-right tabular-nums">{{ Money::format($line['linePence']) }}</span>
                </div>
            @empty
                @unless ($this->reserved)
                    <p class="py-2 text-[0.92rem] text-muted">Your basket is empty. Add products to reserve them.</p>
                @endunless
            @endforelse

            @if ($earnsGift)
                <div class="flex items-center justify-between gap-2 border-b border-dashed border-line py-1.5 text-good">
                    <span>Free {{ mb_strtolower($gift->name) }}</span>
                    <span class="tabular-nums">£0</span>
                </div>
            @elseif ($gift && $lines !== [])
                <p class="mt-2 text-sm text-muted">Spend {{ Money::format($threshold - $total) }} more for a free {{ mb_strtolower($gift->name) }}.</p>
            @endif

            <div class="total">
                <span>Total</span>
                <b>{{ Money::format($total) }}</b>
            </div>

            <form wire:submit="reserve" class="mt-3 grid gap-3" novalidate>
                <label class="field">
                    Name
                    <input type="text" wire:model="name" autocomplete="name" maxlength="60">
                    @error('name') <span class="field-error">{{ $message }}</span> @enderror
                </label>

                <label class="field">
                    Mobile number
                    <input type="tel" wire:model="phone" autocomplete="tel" inputmode="tel" maxlength="25" placeholder="07…">
                    @error('phone') <span class="field-error">{{ $message }}</span> @enderror
                </label>

                <button type="submit" class="btn" wire:loading.attr="disabled" wire:target="reserve" @disabled($lines === [])>Reserve for collection</button>

                @error('reserve') <p class="text-sm text-accent" role="alert">{{ $message }}</p> @enderror
            </form>
        </aside>
    </div>
</div>

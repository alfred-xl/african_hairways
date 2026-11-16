@props(['number'])

<div class="mb-3.5 flex items-baseline gap-2.5">
    <span class="font-display text-[1.4rem] text-accent">{{ $number }}</span>
    <h3>{{ $slot }}</h3>
</div>

<x-layouts.app title="Offers">
    <h2>Offers</h2>
    <p class="lead">Ways to save every time you come in.</p>

    <div class="grid grid-cols-[repeat(auto-fit,minmax(250px,1fr))] gap-4">
        @foreach ($offers as $offer)
            <section class="grid content-start gap-2 rounded-xl border border-l-[6px] border-line border-l-accent bg-surface p-4">
                <h3>{{ $offer['heading'] }}</h3>
                @isset($offer['code'])
                    <span class="justify-self-start rounded-md bg-ink px-2.5 py-0.5 font-display tracking-widest text-page">{{ $offer['code'] }}</span>
                @endisset
                <p>{{ $offer['text'] }}</p>
            </section>
        @endforeach
    </div>

    <p class="mt-6"><a href="{{ route('book') }}" class="btn">Book an appointment</a></p>
</x-layouts.app>

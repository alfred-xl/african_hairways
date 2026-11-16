@props(['title' => null])

<!DOCTYPE html>
<html lang="en-GB">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="theme-color" content="#1b1f2a">
        <meta name="description" content="Book a haircut, braids, twists or locs with your favourite barber.">
        {{ $head ?? '' }}

        <title>{{ $title ? "{$title} · {$shop->shop_name}" : $shop->shop_name }}</title>

        <link rel="manifest" href="/manifest.webmanifest">
        <link rel="icon" href="/icons/icon-192.png">
        <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">

        {{-- Apply a saved light/dark choice before the page paints, so there is no flash. --}}
        <script>
            try {
                const theme = localStorage.getItem('theme');
                if (theme === 'light' || theme === 'dark') document.documentElement.dataset.theme = theme;
            } catch (error) {}
        </script>

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <header class="sticky top-0 z-10 border-b border-line bg-surface pt-[env(safe-area-inset-top)]">
            <div class="wrap flex flex-wrap items-center justify-between gap-x-4 gap-y-2 py-3">
                <a href="{{ route('book') }}" class="flex items-center gap-2.5">
                    <span class="pole" aria-hidden="true"></span>
                    <span>
                        <span class="block font-display text-2xl leading-none font-semibold tracking-wide uppercase">{{ $shop->shop_name }}</span>
                        @if ($shop->tagline)
                            <small class="block text-[0.8rem] text-muted">{{ $shop->tagline }}</small>
                        @endif
                    </span>
                </a>

                <nav aria-label="Sections" class="flex w-full items-center gap-1 overflow-x-auto sm:w-auto">
                    @if (config('shop.main_site_url'))
                        <a href="{{ config('shop.main_site_url') }}" class="rounded-full px-3 py-2 text-[0.95rem] font-medium whitespace-nowrap text-muted hover:bg-soft">← {{ config('shop.main_site_label') }}</a>
                    @endif

                    @foreach (['book' => 'Book', 'prices' => 'Prices', 'shop' => 'Shop', 'offers' => 'Offers'] as $routeName => $label)
                        <a
                            href="{{ route($routeName) }}"
                            @if (request()->routeIs($routeName)) aria-current="page" @endif
                            class="rounded-full px-3 py-2 text-[0.95rem] font-semibold whitespace-nowrap hover:bg-soft aria-[current=page]:bg-ink aria-[current=page]:text-page"
                        >{{ $label }}</a>
                    @endforeach

                    <a href="{{ url('/admin') }}" class="rounded-full px-3 py-2 text-[0.95rem] font-semibold whitespace-nowrap hover:bg-soft">Staff</a>

                    <button type="button" onclick="toggleTheme()" class="ml-auto rounded-full px-3 py-2 text-[0.95rem] whitespace-nowrap text-muted hover:bg-soft sm:ml-0" aria-label="Switch between light and dark mode">
                        <span aria-hidden="true">◐</span>
                    </button>
                </nav>
            </div>
        </header>

        <main class="wrap pt-6 pb-12">
            {{ $slot }}
        </main>

        <footer class="border-t border-line py-5 text-sm text-muted">
            <div class="wrap">
                {{ collect([$shop->address, $shop->phone, $openingHoursLine])->filter()->join(' · ') }}
                <br>
                {{ $shop->shop_name }} is part of
                @if (config('shop.main_site_url'))
                    <a href="{{ config('shop.main_site_url') }}" class="underline">{{ config('shop.company') }}</a>
                @else
                    {{ config('shop.company') }}
                @endif
            </div>
        </footer>
    </body>
</html>

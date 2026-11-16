@php use App\Support\Money; @endphp

<x-layouts.app title="Price list">
    <h2>Price list</h2>
    <p class="lead">Prices are per person for booked appointments. Times are approximate and include a short consultation.</p>

    <div class="grid gap-4 md:grid-cols-2">
        @foreach ($categories as $category)
            <section class="card">
                <h3>{{ $category->name }}</h3>

                <table class="mt-2.5 w-full border-collapse">
                    <tbody>
                        @foreach ($category->services as $service)
                            <tr class="border-b border-dashed border-line align-top">
                                <td class="py-2">
                                    {{ $service->name }}@if ($service->badge)<span class="badge">{{ $service->badge }}</span>@endif
                                    <small class="block text-sm text-muted">{{ collect([$service->description, $service->duration_minutes.' min', $service->is_addon_only ? 'add-on' : null])->filter()->join(' · ') }}</small>
                                </td>
                                <td class="py-2 pl-3 text-right font-bold whitespace-nowrap tabular-nums">
                                    {{ $service->is_addon_only ? '+' : '' }}{{ Money::format($service->price_pence) }}
                                    @if ($service->walk_in_price_pence !== null)
                                        <small class="block text-sm font-normal text-muted">Walk-in {{ Money::format($service->walk_in_price_pence) }}</small>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        @endforeach
    </div>

    <p class="mt-6"><a href="{{ route('book') }}" class="btn">Book an appointment</a></p>
</x-layouts.app>

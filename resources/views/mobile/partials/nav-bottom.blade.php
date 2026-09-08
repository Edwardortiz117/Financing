@php
    $nav = $activeNav ?? 'home';
    $qs = http_build_query(array_filter([
        'year' => $year ?? null,
        'month' => $month ?? null,
    ]));
    $suffix = $qs !== '' ? '?'.$qs : '';
@endphp

<nav class="fixed inset-x-0 bottom-0 z-40 border-t border-border bg-card/95 backdrop-blur" style="padding-bottom: env(safe-area-inset-bottom, 0px)">
    <div class="mx-auto flex max-w-md items-stretch justify-around px-2 pt-2 pb-2">
        <a href="{{ route('home').$suffix }}" class="flex flex-1 flex-col items-center gap-1 py-1 text-[11px] {{ $nav === 'home' ? 'text-brand' : 'text-muted' }}">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1v-9.5z"/>
            </svg>
            <span class="font-medium">Inicio</span>
        </a>
        <a href="{{ route('expenses', array_filter(['year' => $year ?? null, 'month' => $month ?? null, 'tab' => 'categorias'])) }}" class="flex flex-1 flex-col items-center gap-1 py-1 text-[11px] {{ $nav === 'expenses' ? 'text-brand' : 'text-muted' }}">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7h8M8 12h8M8 17h5M5 4h14a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1z"/>
            </svg>
            <span class="font-medium">Mis gastos</span>
        </a>
        <a href="{{ route('more').$suffix }}" class="flex flex-1 flex-col items-center gap-1 py-1 text-[11px] {{ $nav === 'more' ? 'text-brand' : 'text-muted' }}">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M4 12h16M4 17h16"/>
            </svg>
            <span class="font-medium">Más</span>
        </a>
    </div>
</nav>

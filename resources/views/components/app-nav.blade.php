@php
    $links = [
        ['label' => 'Histórico', 'route' => 'history.index', 'active' => 'history.*'],
        ['label' => 'Exercícios', 'route' => 'exercises.index', 'active' => 'exercises.*'],
        ['label' => 'Modelos', 'route' => 'templates.index', 'active' => 'templates.*'],
        ['label' => 'Sync', 'route' => 'sync.index', 'active' => 'sync.*'],
    ];
@endphp

<header class="flex flex-wrap items-center justify-between gap-x-4 gap-y-3 px-5 py-4">
    <a
        href="{{ route('dashboard') }}"
        class="order-1 font-display text-base leading-none font-semibold tracking-wide whitespace-nowrap text-primary uppercase sm:text-lg"
        wire:navigate
    >Diário de Treino</a>

    <nav class="order-3 flex w-full items-center gap-5 border-t border-base-300/40 pt-3 sm:order-2 sm:w-auto sm:border-0 sm:pt-0">
        @foreach ($links as $link)
            <a
                href="{{ route($link['route']) }}"
                @class([
                    'text-sm transition-colors hover:text-primary',
                    'border-b border-primary text-primary' => request()->routeIs($link['active']),
                ])
                wire:navigate
            >{{ $link['label'] }}</a>
        @endforeach
    </nav>

    <form method="POST" action="{{ route('logout') }}" class="order-2 sm:order-3">
        @csrf
        <button type="submit" class="btn btn-outline btn-primary btn-xs px-4">Sair</button>
    </form>
</header>

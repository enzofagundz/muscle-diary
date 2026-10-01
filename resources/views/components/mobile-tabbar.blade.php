@php
    $tabs = [
        ['label' => 'Treinos', 'route' => 'dashboard', 'active' => 'dashboard', 'icon' => '●'],
        ['label' => 'Histórico', 'route' => 'history.index', 'active' => 'history.*', 'icon' => '≡'],
        ['label' => 'Exercícios', 'route' => 'exercises.index', 'active' => 'exercises.*', 'icon' => '◎'],
        ['label' => 'Modelos', 'route' => 'templates.index', 'active' => 'templates.*', 'icon' => '▤'],
    ];
@endphp

<nav
    class="fixed inset-x-0 bottom-0 z-40 border-t border-base-300/40 bg-base-100/95 backdrop-blur"
    style="padding-bottom: env(safe-area-inset-bottom)"
    aria-label="Navegação principal"
>
    <ul class="flex items-stretch">
        @foreach ($tabs as $tab)
            <li class="flex-1">
                <a
                    href="{{ route($tab['route']) }}"
                    @class([
                        'flex flex-col items-center gap-1 px-2 py-3 text-[0.6875rem] tracking-[0.08em] uppercase transition-colors',
                        'text-primary' => request()->routeIs($tab['active']),
                        'opacity-60' => ! request()->routeIs($tab['active']),
                    ])
                    wire:navigate
                >
                    <span class="font-display text-base leading-none">{{ $tab['icon'] }}</span>
                    {{ $tab['label'] }}
                </a>
            </li>
        @endforeach
    </ul>
</nav>

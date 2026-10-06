<div>
    <a href="{{ route('exercises.index') }}" class="text-xs tracking-[0.2em] uppercase opacity-60 hover:text-primary" wire:navigate>
        ← Exercícios
    </a>

    <div class="mt-2 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="font-display text-xs tracking-[0.25em] text-primary uppercase">{{ $exercise->muscle_group }}</p>
            <h1 class="page-title mt-2">{{ $exercise->name }}</h1>
            <p class="mt-2 text-xs opacity-60">
                {{ $executions->count() }} {{ $executions->count() === 1 ? 'execução' : 'execuções' }}
                · carga em {{ $exercise->unit_default->label() }}
            </p>
        </div>
    </div>

    @if ($best)
        <div class="card mt-8 bg-base-200">
            <div class="card-body gap-2 p-5">
                <p class="font-display text-xs tracking-[0.25em] text-primary uppercase">Melhor desempenho</p>
                <p class="font-display text-4xl leading-none">
                    {{ rtrim(rtrim(number_format($best['load'], 2, ',', ''), '0'), ',') }}
                    {{ $best['unit'] }}
                    <span class="opacity-40">×</span>
                    {{ $best['reps'] ?? '—' }}
                </p>
                <p class="text-xs opacity-60">
                    Critério: maior carga, repetições desempatando. Séries de aquecimento não contam.
                    Registrado em {{ \Illuminate\Support\Carbon::parse($best['date'])->translatedFormat('d M Y') }}.
                </p>
            </div>
        </div>
    @endif

    @if ($chart['points'] !== [])
        <section class="card mt-4 bg-base-200">
            <div class="card-body gap-3 p-5">
                <h2 class="font-display text-lg tracking-wide uppercase">Carga ao longo do tempo</h2>

                <svg viewBox="0 0 100 40" preserveAspectRatio="none" class="h-32 w-full" role="img" aria-label="Carga ao longo do tempo">
                    <polyline
                        points="{{ $chart['polyline'] }}"
                        fill="none"
                        stroke="#e894ff"
                        stroke-width="1"
                        vector-effect="non-scaling-stroke"
                        stroke-linejoin="round"
                        stroke-linecap="round"
                    />
                </svg>

                <p class="text-xs opacity-60">
                    Melhor série de cada execução, em {{ $chart['unit'] }}.
                    @if ($chart['points'] !== [])
                        {{ count($chart['points']) }} pontos, de
                        {{ \Illuminate\Support\Carbon::parse($chart['points'][0]['date'])->translatedFormat('d M') }}
                        a {{ \Illuminate\Support\Carbon::parse($chart['points'][count($chart['points']) - 1]['date'])->translatedFormat('d M') }}.
                    @endif
                </p>
            </div>
        </section>
    @endif

    @if ($volumeChart['points'] !== [])
        <section class="card mt-4 bg-base-200">
            <div class="card-body gap-3 p-5">
                <h2 class="font-display text-lg tracking-wide uppercase">Volume ao longo do tempo</h2>

                <svg viewBox="0 0 100 40" preserveAspectRatio="none" class="h-32 w-full" role="img" aria-label="Volume ao longo do tempo">
                    <polyline
                        points="{{ $volumeChart['polyline'] }}"
                        fill="none"
                        stroke="#93ffe4"
                        stroke-width="1"
                        vector-effect="non-scaling-stroke"
                        stroke-linejoin="round"
                        stroke-linecap="round"
                    />
                </svg>

                <p class="text-xs opacity-60">
                    Carga vezes repetições de cada execução, em {{ $volumeChart['unit'] }}, sem as séries de aquecimento.
                </p>
            </div>
        </section>
    @endif

    <ol class="mt-8 flex flex-col gap-3">
        @forelse ($executions->reverse() as $execution)
            <li class="card bg-base-200" wire:key="execution-{{ $execution['item']->id }}">
                <div class="card-body gap-3 p-5">
                    <div class="flex flex-wrap items-baseline justify-between gap-3">
                        <a
                            href="{{ route('history.show', $execution['session']) }}"
                            class="font-display text-xl leading-none uppercase hover:text-primary"
                            wire:navigate
                        >{{ $execution['session']->name }}</a>

                        <p class="text-xs opacity-60">
                            {{ $execution['session']->performed_on->translatedFormat('D, d M Y') }}
                            @if ($execution['session']->location)
                                · {{ $execution['session']->location }}
                            @endif
                        </p>
                    </div>

                    <ul class="flex flex-col gap-1">
                        @forelse ($execution['sets'] as $set)
                            <li class="flex flex-wrap items-baseline gap-x-3 gap-y-1 text-sm">
                                <span class="font-display w-8 shrink-0 opacity-60">
                                    {{ $set->set_number }}{{ $set->part > 0 ? chr(96 + $set->part) : '' }}
                                </span>
                                <span class="font-medium">
                                    {{ $set->load === null ? '—' : rtrim(rtrim(number_format($set->load, 2, ',', ''), '0'), ',') }}
                                    {{ $set->unit->label() }}
                                    ×
                                    {{ $set->reps ?? '—' }}
                                </span>
                                @if ($set->notes)
                                    <span class="min-w-0 break-words opacity-60">{{ $set->notes }}</span>
                                @endif
                            </li>
                        @empty
                            <li class="text-sm opacity-40">Nenhuma série válida nesta execução.</li>
                        @endforelse
                    </ul>
                </div>
            </li>
        @empty
            <li class="rounded-box border border-dashed border-base-300 p-10 text-center text-sm opacity-60">
                Nenhuma execução registrada ainda. Este exercício aparece aqui quando você registrar a primeira série dele.
            </li>
        @endforelse
    </ol>
</div>

<div>
    <p class="font-display text-xs tracking-[0.25em] text-primary uppercase">Histórico</p>
    <h1 class="page-title mt-2">Treinos</h1>

    @if ($unfinished->isNotEmpty())
        <section class="mt-8">
            <h2 class="font-display text-lg tracking-wide uppercase">Não concluídos</h2>

            <ul class="mt-3 flex flex-col gap-2">
                @foreach ($unfinished as $session)
                    <li class="card bg-base-200" wire:key="unfinished-{{ $session->id }}">
                        <div class="card-body flex-row items-center justify-between gap-3 p-5">
                            <div class="min-w-0">
                                <p class="truncate font-medium">{{ $session->name }}</p>
                                <p class="mt-1 text-xs opacity-60">
                                    {{ $session->performed_on->translatedFormat('D, d M') }}
                                    @if ($session->location)
                                        · {{ $session->location }}
                                    @endif
                                </p>
                            </div>

                            <a href="{{ route('sessions.run', $session) }}" class="btn btn-outline btn-primary btn-xs px-4 shrink-0" wire:navigate>
                                Retomar
                            </a>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @forelse ($weeks as $week)
        <section class="mt-8" wire:key="week-{{ $week['start']->toDateString() }}">
            <div class="flex items-baseline justify-between gap-3">
                <h2 class="font-display text-lg tracking-wide uppercase">
                    {{ $week['start']->translatedFormat('d M') }} – {{ $week['end']->translatedFormat('d M') }}
                </h2>
                <span class="text-xs opacity-60">
                    {{ $week['sessions']->count() }} {{ $week['sessions']->count() === 1 ? 'treino' : 'treinos' }}
                </span>
            </div>

            @if ($week['sessions']->isEmpty())
                <p class="mt-3 rounded-box border border-dashed border-base-300 p-5 text-center text-sm opacity-40">
                    Semana sem treino.
                </p>
            @else
                <ul class="mt-3 flex flex-col gap-2">
                    @foreach ($week['sessions'] as $session)
                        <li class="card bg-base-200" wire:key="session-{{ $session->id }}">
                            <div class="card-body flex-row items-center justify-between gap-3 p-5">
                                <div class="min-w-0">
                                    <a
                                        href="{{ route('history.show', $session) }}"
                                        class="font-display text-xl leading-none uppercase hover:text-primary"
                                        wire:navigate
                                    >{{ $session->name }}</a>

                                    <p class="mt-2 text-xs opacity-60">
                                        {{ $session->performed_on->translatedFormat('D, d M') }}
                                        @if ($session->location)
                                            · {{ $session->location }}
                                        @endif
                                        @if ($session->durationInMinutes())
                                            · {{ $session->durationInMinutes() }} min
                                        @endif
                                    </p>
                                </div>

                                <a href="{{ route('history.show', $session) }}" class="btn btn-ghost btn-xs shrink-0" wire:navigate>
                                    Abrir
                                </a>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @empty
        <div class="mt-8 rounded-box border border-dashed border-base-300 p-10 text-center text-sm opacity-60">
            Nenhum treino concluído ainda.
        </div>
    @endforelse
</div>

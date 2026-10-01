<div>
    <p class="font-display text-xs tracking-[0.25em] text-primary uppercase">Em andamento</p>
    <h1 class="page-title mt-2">{{ $session->name }}</h1>
    <p class="mt-2 text-xs opacity-60">
        {{ $session->performed_on->translatedFormat('D, d M') }}
        @if ($session->location)
            · {{ $session->location }}
        @endif
        · descanso {{ $session->rest_seconds }}s
    </p>

    <ol class="mt-8 flex flex-col gap-3">
        @forelse ($items as $index => $item)
            <li class="card bg-base-200" wire:key="{{ $item->id }}">
                <div class="card-body gap-3 p-5">
                    <div class="flex items-baseline gap-3">
                        <span class="font-display text-2xl leading-none text-primary">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</span>
                        <div class="min-w-0">
                            <p class="truncate font-medium">{{ $item->exercise->name }}</p>
                            <p class="mt-1 text-xs opacity-60">
                                {{ $item->planned_sets }} séries
                                @if ($item->rep_min || $item->rep_max)
                                    · {{ $item->rep_min ?? '?' }}–{{ $item->rep_max ?? '?' }} reps
                                @endif
                                · descanso {{ $item->rest_seconds ?? $session->rest_seconds }}s
                            </p>
                        </div>
                    </div>

                    @if ($item->notes)
                        <p class="text-sm opacity-70">{{ $item->notes }}</p>
                    @endif
                </div>
            </li>
        @empty
            <li class="rounded-box border border-dashed border-base-300 p-10 text-center text-sm opacity-60">
                Treino livre, sem exercícios planejados.
            </li>
        @endforelse
    </ol>
</div>

<div>
    <a href="{{ route('history.index') }}" class="text-xs tracking-[0.2em] uppercase opacity-60 hover:text-primary" wire:navigate>
        ← Histórico
    </a>

    <div class="mt-2 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="page-title">{{ $session->name }}</h1>
            <p class="mt-2 text-xs opacity-60">
                {{ $session->performed_on->translatedFormat('D, d M') }}
                @if ($session->location)
                    · {{ $session->location }}
                @endif
                @if ($session->durationInMinutes())
                    · {{ $session->durationInMinutes() }} min
                @endif
                @unless ($session->isFinished())
                    · <span class="badge badge-outline badge-primary badge-xs">não concluído</span>
                @endunless
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('sessions.run', $session) }}" class="btn btn-outline btn-primary btn-sm px-5" wire:navigate>
                Corrigir séries
            </a>
            <button type="button" class="btn btn-ghost btn-sm" wire:click="edit">Editar dados</button>
            <button
                type="button"
                class="btn btn-ghost btn-sm text-error"
                wire:click="delete"
                wire:confirm="Apagar este treino? As séries registradas nele vão junto."
            >
                Apagar
            </button>
        </div>
    </div>

    @if ($formOpen)
        <form wire:submit="save" class="card mt-6 bg-base-200">
            <div class="card-body gap-4">
                <h2 class="card-title text-xl">Editar treino</h2>

                <label class="fieldset">
                    <span class="label">Nome</span>
                    <input type="text" class="input w-full" wire:model="name" required>
                    @error('name') <span class="text-error text-sm">{{ $message }}</span> @enderror
                </label>

                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="fieldset">
                        <span class="label">Data</span>
                        <input type="date" class="input w-full" wire:model="performedOn" required>
                        @error('performedOn') <span class="text-error text-sm">{{ $message }}</span> @enderror
                    </label>

                    <label class="fieldset">
                        <span class="label">Local</span>
                        <input type="text" class="input w-full" wire:model="location">
                        @error('location') <span class="text-error text-sm">{{ $message }}</span> @enderror
                    </label>
                </div>

                <label class="fieldset">
                    <span class="label">Observações</span>
                    <textarea class="textarea w-full" rows="2" wire:model="notes"></textarea>
                    @error('notes') <span class="text-error text-sm">{{ $message }}</span> @enderror
                </label>

                <div class="card-actions justify-end">
                    <button type="button" class="btn btn-ghost" wire:click="cancel">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-6" wire:loading.attr="disabled">Salvar</button>
                </div>
            </div>
        </form>
    @endif

    @if ($session->notes)
        <p class="mt-6 rounded-box bg-base-200 p-5 text-sm">{{ $session->notes }}</p>
    @endif

    <label class="fieldset mt-8 max-w-md">
        <span class="label">Comparar com</span>
        <select class="select w-full" wire:model.live="compareWithId">
            <option value="">Não comparar</option>
            @foreach ($candidates as $candidate)
                <option value="{{ $candidate->id }}">
                    {{ $candidate->performed_on->translatedFormat('d M') }} · {{ $candidate->name }}
                </option>
            @endforeach
        </select>
    </label>

    @if ($comparingWith === null)
        <p class="mt-3 text-sm opacity-60">Sem treino anterior para comparar.</p>
    @else
        <section class="mt-6">
            <h2 class="font-display text-lg tracking-wide uppercase">
                Comparação com {{ $comparingWith->performed_on->translatedFormat('d M') }}
            </h2>

            <ul class="mt-3 flex flex-col gap-3">
                @foreach ($comparison as $row)
                    <li class="card bg-base-200" wire:key="compare-{{ $row['item']->id }}">
                        <div class="card-body gap-4 p-5">
                            <div class="flex flex-wrap items-baseline justify-between gap-3">
                                <p class="font-display text-xl leading-none uppercase">
                                    {{ $row['item']->exercise->name }}
                                </p>

                                @if ($row['delta'])
                                    @php $load = rtrim(rtrim(number_format($row['delta']['load'], 2, ',', ''), '0'), ','); @endphp
                                    <span
                                        @class([
                                            'font-display text-lg leading-none',
                                            'text-primary' => $row['delta']['load'] > 0 || $row['delta']['reps'] > 0,
                                            'text-error' => $row['delta']['load'] < 0 || $row['delta']['reps'] < 0,
                                            'opacity-40' => $row['delta']['load'] === 0.0 && $row['delta']['reps'] === 0,
                                        ])
                                    >
                                        {{ $row['delta']['load'] > 0 ? '+' : '' }}{{ $load }} carga
                                        ·
                                        {{ $row['delta']['reps'] > 0 ? '+' : '' }}{{ $row['delta']['reps'] }} reps
                                    </span>
                                @else
                                    <span class="text-xs opacity-50">sem comparação direta</span>
                                @endif
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <p class="text-xs tracking-[0.2em] uppercase opacity-50">Antes</p>
                                    <ul class="mt-2 flex flex-col gap-1">
                                        @forelse ($row['previousSets'] as $set)
                                            <li class="text-sm">
                                                <span class="font-display mr-2 opacity-60">{{ $set->set_number }}{{ $set->part > 0 ? chr(96 + $set->part) : '' }}</span>
                                                {{ $set->load === null ? '—' : rtrim(rtrim(number_format($set->load, 2, ',', ''), '0'), ',') }}
                                                {{ $set->unit->label() }} × {{ $set->reps ?? '—' }}
                                            </li>
                                        @empty
                                            <li class="text-sm opacity-40">—</li>
                                        @endforelse
                                    </ul>
                                </div>

                                <div>
                                    <p class="text-xs tracking-[0.2em] uppercase opacity-50">Agora</p>
                                    <ul class="mt-2 flex flex-col gap-1">
                                        @forelse ($row['currentSets'] as $set)
                                            <li class="text-sm">
                                                <span class="font-display mr-2 opacity-60">{{ $set->set_number }}{{ $set->part > 0 ? chr(96 + $set->part) : '' }}</span>
                                                {{ $set->load === null ? '—' : rtrim(rtrim(number_format($set->load, 2, ',', ''), '0'), ',') }}
                                                {{ $set->unit->label() }} × {{ $set->reps ?? '—' }}
                                            </li>
                                        @empty
                                            <li class="text-sm opacity-40">—</li>
                                        @endforelse
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <ol class="mt-8 flex flex-col gap-3">
        @forelse ($items as $index => $item)
            <li class="card bg-base-200" wire:key="item-{{ $item->id }}">
                <div class="card-body gap-3 p-5">
                    <div class="flex items-baseline gap-3">
                        <span class="font-display text-2xl leading-none text-primary">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</span>
                        <div class="min-w-0">
                            <p class="truncate font-display text-2xl leading-none uppercase">{{ $item->exercise->name }}</p>
                            <p class="mt-2 text-xs opacity-60">
                                {{ $item->setsCount() }} {{ $item->setsCount() === 1 ? 'série' : 'séries' }}
                                @if ($item->rep_min || $item->rep_max)
                                    · {{ $item->rep_min ?? '?' }}–{{ $item->rep_max ?? '?' }} reps
                                @endif
                            </p>
                        </div>
                    </div>

                    <ul class="flex flex-col gap-1">
                        @forelse ($item->sets as $set)
                            <li class="flex flex-wrap items-baseline gap-x-3 gap-y-1 text-sm" wire:key="set-{{ $set->id }}">
                                <span class="font-display w-8 shrink-0 opacity-60">
                                    {{ $set->set_number }}{{ $set->part > 0 ? chr(96 + $set->part) : '' }}
                                </span>
                                <span class="font-medium">
                                    {{ $set->load === null ? '—' : rtrim(rtrim(number_format($set->load, 2, ',', ''), '0'), ',') }}
                                    {{ $set->unit->label() }}
                                    ×
                                    {{ $set->reps ?? '—' }}
                                </span>
                                @if ($set->is_warmup)
                                    <span class="badge badge-ghost badge-xs">aquecimento</span>
                                @endif
                                @if ($set->notes)
                                    <span class="min-w-0 break-words opacity-60">{{ $set->notes }}</span>
                                @endif
                            </li>
                        @empty
                            <li class="text-sm opacity-40">Nenhuma série registrada.</li>
                        @endforelse
                    </ul>

                    @if ($item->notes)
                        <p class="text-sm opacity-70">{{ $item->notes }}</p>
                    @endif
                </div>
            </li>
        @empty
            <li class="rounded-box border border-dashed border-base-300 p-10 text-center text-sm opacity-60">
                Nenhum exercício neste treino.
            </li>
        @endforelse
    </ol>
</div>

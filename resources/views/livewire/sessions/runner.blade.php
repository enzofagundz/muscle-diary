<div>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <a href="{{ route('dashboard') }}" class="text-xs tracking-[0.2em] uppercase opacity-60 hover:text-primary" wire:navigate>
                ← Treinos
            </a>
            <h1 class="page-title mt-2">{{ $session->name }}</h1>
            <p class="mt-2 text-xs opacity-60">
                {{ $session->performed_on->translatedFormat('D, d M') }}
                @if ($session->location)
                    · {{ $session->location }}
                @endif
                · descanso {{ $session->rest_seconds }}s
            </p>
        </div>

        <button type="button" class="btn btn-primary btn-sm px-5" wire:click="addItem">Adicionar exercício</button>
    </div>

    @if ($formOpen)
        <form wire:submit="saveItem" class="card mt-6 bg-base-200">
            <div class="card-body gap-4">
                <h2 class="card-title text-xl">Adicionar exercício</h2>

                <label class="fieldset">
                    <span class="label">Exercício</span>
                    <select class="select w-full" wire:model="exerciseId" required>
                        <option value="">Escolha…</option>
                        @foreach ($exercises as $exercise)
                            <option value="{{ $exercise->id }}">{{ $exercise->muscle_group }} · {{ $exercise->name }}</option>
                        @endforeach
                    </select>
                    @error('exerciseId') <span class="text-error text-sm">{{ $message }}</span> @enderror
                </label>

                <div class="grid gap-4 sm:grid-cols-3">
                    <label class="fieldset">
                        <span class="label">Séries planejadas</span>
                        <input type="number" min="1" max="20" class="input w-full" wire:model="plannedSets" required>
                        @error('plannedSets') <span class="text-error text-sm">{{ $message }}</span> @enderror
                    </label>

                    <label class="fieldset">
                        <span class="label">Rep. mín</span>
                        <input type="number" min="1" max="100" class="input w-full" wire:model="repMin">
                        @error('repMin') <span class="text-error text-sm">{{ $message }}</span> @enderror
                    </label>

                    <label class="fieldset">
                        <span class="label">Rep. máx</span>
                        <input type="number" min="1" max="100" class="input w-full" wire:model="repMax">
                        @error('repMax') <span class="text-error text-sm">{{ $message }}</span> @enderror
                    </label>
                </div>

                <div class="card-actions justify-end">
                    <button type="button" class="btn btn-ghost" wire:click="cancelItem">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-6" wire:loading.attr="disabled">Adicionar</button>
                </div>
            </div>
        </form>
    @endif

    <ol class="mt-8 flex flex-col gap-4">
        @forelse ($items as $index => $item)
            <li class="card bg-base-200" wire:key="item-{{ $item->id }}">
                <div class="card-body gap-4 p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex min-w-0 items-baseline gap-3">
                            <span class="font-display text-2xl leading-none text-primary">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</span>
                            <div class="min-w-0">
                                <p class="truncate font-display text-2xl leading-none uppercase">{{ $item->exercise->name }}</p>
                                <p class="mt-2 text-xs opacity-60">
                                    {{ $item->planned_sets }} séries
                                    @if ($item->rep_min || $item->rep_max)
                                        · {{ $item->rep_min ?? '?' }}–{{ $item->rep_max ?? '?' }} reps
                                    @endif
                                    · descanso {{ $item->rest_seconds ?? $session->rest_seconds }}s
                                </p>
                            </div>
                        </div>

                        <button
                            type="button"
                            class="btn btn-ghost btn-xs text-error"
                            wire:click="removeItem('{{ $item->id }}')"
                            wire:confirm="Remover este exercício do treino?"
                        >
                            Remover
                        </button>
                    </div>

                    <div class="flex flex-col gap-2">
                        @foreach ($item->sets as $set)
                            @php $draft = $setDrafts[$set->id] ?? []; @endphp

                            <div class="rounded-box border border-base-300/60 p-3" wire:key="set-{{ $set->id }}">
                                <div class="flex items-center gap-2">
                                    <span class="font-display w-8 shrink-0 text-lg leading-none opacity-60">
                                        {{ $set->set_number }}{{ $set->part > 0 ? chr(97 + $set->part) : '' }}
                                    </span>

                                    <input
                                        type="text"
                                        inputmode="decimal"
                                        class="input input-lg w-24 text-center"
                                        placeholder="—"
                                        wire:model.live.debounce.500ms="setDrafts.{{ $set->id }}.load"
                                        aria-label="Carga"
                                    >

                                    <select
                                        class="select select-sm w-28 shrink-0"
                                        wire:model.blur="setDrafts.{{ $set->id }}.unit"
                                        aria-label="Unidade"
                                    >
                                        @foreach ($units as $unit)
                                            <option value="{{ $unit->value }}">{{ $unit->label() }}</option>
                                        @endforeach
                                    </select>

                                    <span class="opacity-40">×</span>

                                    <input
                                        type="text"
                                        inputmode="numeric"
                                        class="input input-lg w-20 text-center"
                                        placeholder="—"
                                        wire:model.live.debounce.500ms="setDrafts.{{ $set->id }}.reps"
                                        aria-label="Repetições"
                                    >
                                </div>

                                @error("setDrafts.{$set->id}.load")
                                    <p class="text-error mt-2 text-xs">{{ $message }}</p>
                                @enderror
                                @error("setDrafts.{$set->id}.reps")
                                    <p class="text-error mt-2 text-xs">{{ $message }}</p>
                                @enderror

                                <div class="mt-3 flex flex-wrap items-center gap-3">
                                    <label class="flex cursor-pointer items-center gap-2 text-xs">
                                        <input type="checkbox" class="checkbox checkbox-xs" wire:model.live="setDrafts.{{ $set->id }}.is_warmup">
                                        aquecimento
                                    </label>

                                    <button type="button" class="btn btn-ghost btn-xs" wire:click="copyPrevious('{{ $set->id }}')">
                                        copiar anterior
                                    </button>

                                    <button type="button" class="btn btn-ghost btn-xs text-error" wire:click="removeSet('{{ $set->id }}')">
                                        remover
                                    </button>
                                </div>

                                <input
                                    type="text"
                                    class="input input-sm mt-3 w-full"
                                    placeholder="Observação da série"
                                    wire:model.live.debounce.500ms="setDrafts.{{ $set->id }}.notes"
                                    aria-label="Observação da série"
                                >
                            </div>
                        @endforeach
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="btn btn-outline btn-primary btn-sm px-5" wire:click="addSet('{{ $item->id }}')">
                            + série
                        </button>
                    </div>

                    <input
                        type="text"
                        class="input w-full"
                        placeholder="Observação do exercício"
                        wire:model.live.debounce.500ms="itemNotes.{{ $item->id }}"
                        aria-label="Observação do exercício"
                    >
                </div>
            </li>
        @empty
            <li class="rounded-box border border-dashed border-base-300 p-10 text-center text-sm opacity-60">
                Treino livre, sem exercícios planejados. Adicione o primeiro.
            </li>
        @endforelse
    </ol>

    <label class="fieldset mt-8">
        <span class="label">Observações do treino</span>
        <textarea
            class="textarea w-full"
            rows="2"
            placeholder="Como foi o treino"
            wire:model.live.debounce.500ms="sessionNotes"
        ></textarea>
    </label>
</div>

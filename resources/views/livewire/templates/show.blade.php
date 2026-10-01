<div>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <a href="{{ route('templates.index') }}" class="text-xs tracking-[0.2em] uppercase opacity-60 hover:text-primary" wire:navigate>
                ← Modelos
            </a>
            <h1 class="page-title mt-2">{{ $template->name }}</h1>
            <p class="mt-2 text-xs opacity-60">
                descanso padrão {{ $template->rest_seconds }}s
                @unless ($template->is_active)
                    · <span class="badge badge-outline badge-primary badge-xs">fora da rotação</span>
                @endunless
            </p>
        </div>

        <button type="button" class="btn btn-primary btn-sm px-5" wire:click="addItem">Adicionar exercício</button>
    </div>

    @if ($template->notes)
        <p class="mt-6 rounded-box bg-base-200 p-5 text-sm">{{ $template->notes }}</p>
    @endif

    @if ($formOpen)
        <form wire:submit="saveItem" class="card mt-6 bg-base-200">
            <div class="card-body gap-4">
                <h2 class="card-title text-xl">{{ $editingId ? 'Editar exercício' : 'Adicionar exercício' }}</h2>

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

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <label class="fieldset">
                        <span class="label">Séries</span>
                        <input type="number" min="1" max="20" class="input w-full" wire:model="sets" required>
                        @error('sets') <span class="text-error text-sm">{{ $message }}</span> @enderror
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

                    <label class="fieldset">
                        <span class="label">Descanso (s)</span>
                        <input type="number" min="0" max="3600" class="input w-full" wire:model="restSeconds" placeholder="{{ $template->rest_seconds }}">
                        @error('restSeconds') <span class="text-error text-sm">{{ $message }}</span> @enderror
                    </label>
                </div>

                <label class="fieldset">
                    <span class="label">Observações</span>
                    <textarea class="textarea w-full" rows="2" wire:model="notes" placeholder="Pegada, cadência, ordem…"></textarea>
                    @error('notes') <span class="text-error text-sm">{{ $message }}</span> @enderror
                </label>

                <div class="card-actions justify-end">
                    <button type="button" class="btn btn-ghost" wire:click="cancelItem">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-6" wire:loading.attr="disabled">Salvar</button>
                </div>
            </div>
        </form>
    @endif

    <ol class="mt-8 flex flex-col gap-3">
        @forelse ($items as $index => $item)
            <li class="card bg-base-200" wire:key="{{ $item->id }}">
                <div class="card-body gap-4 p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex min-w-0 items-baseline gap-3">
                            <span class="font-display text-2xl leading-none text-primary">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</span>
                            <div class="min-w-0">
                                <p class="truncate font-medium">{{ $item->exercise->name }}</p>
                                <p class="mt-1 text-xs opacity-60">
                                    {{ $item->sets }} séries
                                    @if ($item->rep_min || $item->rep_max)
                                        · {{ $item->rep_min ?? '?' }}–{{ $item->rep_max ?? '?' }} reps
                                    @endif
                                    · descanso {{ $item->rest_seconds ?? $template->rest_seconds }}s
                                </p>
                            </div>
                        </div>

                        <div class="flex shrink-0 gap-1">
                            <button type="button" class="btn btn-ghost btn-xs" wire:click="moveUp('{{ $item->id }}')" aria-label="Subir">↑</button>
                            <button type="button" class="btn btn-ghost btn-xs" wire:click="moveDown('{{ $item->id }}')" aria-label="Descer">↓</button>
                        </div>
                    </div>

                    @if ($item->notes)
                        <p class="text-sm opacity-70">{{ $item->notes }}</p>
                    @endif

                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="btn btn-ghost btn-xs" wire:click="editItem('{{ $item->id }}')">Editar</button>
                        <button
                            type="button"
                            class="btn btn-ghost btn-xs text-error"
                            wire:click="removeItem('{{ $item->id }}')"
                            wire:confirm="Remover este exercício do modelo?"
                        >
                            Remover
                        </button>
                    </div>
                </div>
            </li>
        @empty
            <li class="rounded-box border border-dashed border-base-300 p-10 text-center text-sm opacity-60">
                Nenhum exercício neste modelo.
            </li>
        @endforelse
    </ol>
</div>

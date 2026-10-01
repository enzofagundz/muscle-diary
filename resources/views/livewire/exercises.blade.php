<div>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="font-display text-xs tracking-[0.25em] text-primary uppercase">Catálogo</p>
            <h1 class="page-title mt-2">Exercícios</h1>
        </div>

        <div class="flex items-center gap-2">
            <button type="button" class="btn btn-ghost btn-sm" wire:click="$toggle('showArchived')">
                {{ $showArchived ? 'Ver ativos' : "Arquivados ({$archivedCount})" }}
            </button>
            <button type="button" class="btn btn-primary btn-sm px-5" wire:click="create">Novo</button>
        </div>
    </div>

    @if ($notice)
        <div class="mt-6 rounded-box bg-base-200 p-5 text-sm" role="status">{{ $notice }}</div>
    @endif

    @if ($formOpen)
        <form wire:submit="save" class="card mt-6 bg-base-200">
            <div class="card-body gap-4">
                <h2 class="card-title text-xl">{{ $editingId ? 'Editar exercício' : 'Novo exercício' }}</h2>

                <label class="fieldset">
                    <span class="label">Nome</span>
                    <input type="text" class="input w-full" wire:model="name" required>
                    @error('name') <span class="text-error text-sm">{{ $message }}</span> @enderror
                </label>

                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="fieldset">
                        <span class="label">Grupo muscular</span>
                        <select class="select w-full" wire:model="muscleGroup" required>
                            <option value="">Escolha…</option>
                            @foreach ($groups as $group)
                                <option value="{{ $group }}">{{ $group }}</option>
                            @endforeach
                        </select>
                        @error('muscleGroup') <span class="text-error text-sm">{{ $message }}</span> @enderror
                    </label>

                    <label class="fieldset">
                        <span class="label">Unidade padrão</span>
                        <select class="select w-full" wire:model="unitDefault" required>
                            @foreach ($units as $unit)
                                <option value="{{ $unit->value }}">{{ $unit->label() }}</option>
                            @endforeach
                        </select>
                        @error('unitDefault') <span class="text-error text-sm">{{ $message }}</span> @enderror
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

    @unless ($showArchived)
        <div class="mt-6 flex flex-col gap-3 sm:flex-row">
            <input
                type="search"
                class="input w-full"
                placeholder="Buscar por nome"
                wire:model.live.debounce.300ms="search"
            >

            <select class="select w-full sm:w-64" wire:model.live="group">
                <option value="">Todos os grupos</option>
                @foreach ($groups as $muscleGroup)
                    <option value="{{ $muscleGroup }}">{{ $muscleGroup }}</option>
                @endforeach
            </select>
        </div>
    @endunless

    <ul class="mt-6 grid gap-2 sm:grid-cols-2">
        @forelse ($exercises as $exercise)
            <li class="card bg-base-200" wire:key="{{ $exercise->id }}">
                <div class="card-body flex-row items-center justify-between gap-3 p-5">
                    <div class="min-w-0">
                        <a
                            href="{{ route('exercises.show', $exercise) }}"
                            class="truncate font-medium hover:text-primary"
                            wire:navigate
                        >{{ $exercise->name }}</a>
                        <p class="mt-1 text-xs opacity-60">
                            {{ $exercise->muscle_group }} · {{ $exercise->unit_default->label() }}
                            @if ($exercise->isGlobal())
                                · <span class="badge badge-ghost badge-xs">catálogo base</span>
                            @endif
                        </p>
                    </div>

                    <div class="flex shrink-0 gap-1">
                        @if ($showArchived)
                            <button type="button" class="btn btn-ghost btn-xs" wire:click="restore('{{ $exercise->id }}')">
                                Restaurar
                            </button>
                        @else
                            <button type="button" class="btn btn-ghost btn-xs" wire:click="edit('{{ $exercise->id }}')">
                                Editar
                            </button>

                            @unless ($exercise->isGlobal())
                                <button
                                    type="button"
                                    class="btn btn-ghost btn-xs text-error"
                                    wire:click="archive('{{ $exercise->id }}')"
                                    wire:confirm="Arquivar este exercício? O histórico dele continua guardado."
                                >
                                    Arquivar
                                </button>
                            @endunless
                        @endif
                    </div>
                </div>
            </li>
        @empty
            <li class="col-span-full rounded-box border border-dashed border-base-300 p-10 text-center text-sm opacity-60">
                {{ $showArchived ? 'Nenhum exercício arquivado.' : 'Nenhum exercício encontrado.' }}
            </li>
        @endforelse
    </ul>
</div>

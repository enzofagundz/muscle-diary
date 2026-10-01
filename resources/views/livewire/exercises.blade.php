<div>
    <div class="flex items-center justify-between gap-2">
        <h1 class="text-2xl font-semibold">Exercícios</h1>

        <div class="flex items-center gap-2">
            <button type="button" class="btn btn-ghost btn-sm" wire:click="$toggle('showArchived')">
                {{ $showArchived ? 'Ver ativos' : "Arquivados ({$archivedCount})" }}
            </button>
            <button type="button" class="btn btn-primary btn-sm" wire:click="create">Novo</button>
        </div>
    </div>

    @if ($notice)
        <div class="alert alert-info mt-4 text-sm">{{ $notice }}</div>
    @endif

    @if ($formOpen)
        <form wire:submit="save" class="card mt-4 border border-base-300 bg-base-100">
            <div class="card-body gap-3">
                <h2 class="card-title text-base">{{ $editingId ? 'Editar exercício' : 'Novo exercício' }}</h2>

                <label class="fieldset">
                    <span class="label">Nome</span>
                    <input type="text" class="input w-full" wire:model="name" required>
                    @error('name') <span class="text-error text-sm">{{ $message }}</span> @enderror
                </label>

                <div class="grid gap-3 sm:grid-cols-2">
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
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Salvar</button>
                </div>
            </div>
        </form>
    @endif

    @unless ($showArchived)
        <div class="mt-4 flex flex-col gap-2 sm:flex-row">
            <input
                type="search"
                class="input w-full"
                placeholder="Buscar por nome"
                wire:model.live.debounce.300ms="search"
            >

            <select class="select w-full sm:w-56" wire:model.live="group">
                <option value="">Todos os grupos</option>
                @foreach ($groups as $muscleGroup)
                    <option value="{{ $muscleGroup }}">{{ $muscleGroup }}</option>
                @endforeach
            </select>
        </div>
    @endunless

    <ul class="mt-4 flex flex-col gap-2">
        @forelse ($exercises as $exercise)
            <li class="card border border-base-300 bg-base-100" wire:key="{{ $exercise->id }}">
                <div class="card-body flex-row items-center justify-between gap-2 p-3">
                    <div class="min-w-0">
                        <p class="truncate font-medium">{{ $exercise->name }}</p>
                        <p class="text-xs opacity-70">
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
            <li class="rounded-box border border-dashed border-base-300 p-6 text-center text-sm opacity-70">
                {{ $showArchived ? 'Nenhum exercício arquivado.' : 'Nenhum exercício encontrado.' }}
            </li>
        @endforelse
    </ul>
</div>

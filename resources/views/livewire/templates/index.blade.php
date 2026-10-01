<div>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="font-display text-xs tracking-[0.25em] text-primary uppercase">Rotação</p>
            <h1 class="page-title mt-2">Modelos</h1>
        </div>

        <button type="button" class="btn btn-primary btn-sm px-5" wire:click="create">Novo modelo</button>
    </div>

    @if ($formOpen)
        <form wire:submit="save" class="card mt-6 bg-base-200">
            <div class="card-body gap-4">
                <h2 class="card-title text-xl">{{ $editingId ? 'Editar modelo' : 'Novo modelo' }}</h2>

                <label class="fieldset">
                    <span class="label">Nome</span>
                    <input type="text" class="input w-full" wire:model="name" placeholder="Upper 1" required>
                    @error('name') <span class="text-error text-sm">{{ $message }}</span> @enderror
                </label>

                <label class="fieldset">
                    <span class="label">Descanso padrão (segundos)</span>
                    <input type="number" min="0" max="3600" class="input w-full" wire:model="restSeconds" required>
                    @error('restSeconds') <span class="text-error text-sm">{{ $message }}</span> @enderror
                </label>

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

    <ul class="mt-8 flex flex-col gap-3">
        @forelse ($templates as $template)
            <li class="card bg-base-200" wire:key="{{ $template->id }}">
                <div class="card-body gap-4 p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <a
                                href="{{ route('templates.edit', $template) }}"
                                class="font-display text-2xl leading-none uppercase hover:text-primary"
                                wire:navigate
                            >{{ $template->name }}</a>

                            <p class="mt-2 text-xs opacity-60">
                                {{ $template->items_count }} exercícios · descanso {{ $template->rest_seconds }}s
                                @unless ($template->is_active)
                                    · <span class="badge badge-outline badge-primary badge-xs">fora da rotação</span>
                                @endunless
                            </p>
                        </div>

                        <div class="flex shrink-0 gap-1">
                            <button type="button" class="btn btn-ghost btn-xs" wire:click="moveUp('{{ $template->id }}')" aria-label="Subir">↑</button>
                            <button type="button" class="btn btn-ghost btn-xs" wire:click="moveDown('{{ $template->id }}')" aria-label="Descer">↓</button>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('templates.edit', $template) }}" class="btn btn-outline btn-primary btn-xs px-4" wire:navigate>
                            Exercícios
                        </a>
                        <button type="button" class="btn btn-ghost btn-xs" wire:click="edit('{{ $template->id }}')">Renomear</button>
                        <button type="button" class="btn btn-ghost btn-xs" wire:click="duplicate('{{ $template->id }}')">Duplicar</button>
                        <button type="button" class="btn btn-ghost btn-xs" wire:click="toggleActive('{{ $template->id }}')">
                            {{ $template->is_active ? 'Tirar da rotação' : 'Voltar para a rotação' }}
                        </button>
                        <button
                            type="button"
                            class="btn btn-ghost btn-xs text-error"
                            wire:click="delete('{{ $template->id }}')"
                            wire:confirm="Apagar este modelo? Os treinos já registrados com ele continuam guardados."
                        >
                            Apagar
                        </button>
                    </div>
                </div>
            </li>
        @empty
            <li class="rounded-box border border-dashed border-base-300 p-10 text-center text-sm opacity-60">
                Nenhum modelo ainda. Crie o primeiro, como "Upper 1".
            </li>
        @endforelse
    </ul>
</div>

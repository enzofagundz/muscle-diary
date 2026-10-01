<div>
    @if ($inProgress)
        <div class="card bg-base-200">
            <div class="card-body gap-4 p-5">
                <div>
                    <p class="font-display text-xs tracking-[0.25em] text-primary uppercase">Em andamento</p>
                    <p class="mt-2 font-display text-3xl leading-none uppercase">{{ $inProgress->name }}</p>
                    <p class="mt-2 text-xs opacity-60">
                        {{ $inProgress->performed_on->translatedFormat('D, d M') }}
                        @if ($inProgress->location)
                            · {{ $inProgress->location }}
                        @endif
                    </p>
                </div>

                <div>
                    <a href="{{ route('sessions.run', $inProgress) }}" class="btn btn-primary px-6" wire:navigate>
                        Retomar
                    </a>
                </div>
            </div>
        </div>
    @endif

    <div class="mt-8">
        <p class="font-display text-xs tracking-[0.25em] text-primary uppercase">Próximo treino</p>
        <h1 class="page-title mt-2">{{ $next?->name ?? 'Sem modelo' }}</h1>

        @if ($next)
            <p class="mt-2 text-xs opacity-60">
                {{ $next->items()->count() }} exercícios · descanso {{ $next->rest_seconds }}s
            </p>
        @endif
    </div>

    @if ($templates->isEmpty())
        <div class="mt-8 rounded-box border border-dashed border-base-300 p-10 text-center text-sm opacity-60">
            Nenhum modelo ativo. Crie um em
            <a href="{{ route('templates.index') }}" class="link link-primary" wire:navigate>Modelos</a>
            para começar a girar a rotação.
        </div>
    @else
        <form wire:submit="start" class="card mt-8 bg-base-200">
            <div class="card-body gap-5 p-5">
                <h2 class="card-title text-xl">Começar treino</h2>

                <label class="fieldset">
                    <span class="label">Modelo</span>
                    <select class="select w-full" wire:model="templateId">
                        <option value="">Treino livre (sem modelo)</option>
                        @foreach ($templates as $template)
                            <option value="{{ $template->id }}">{{ $template->name }}</option>
                        @endforeach
                    </select>
                    @error('templateId') <span class="text-error text-sm">{{ $message }}</span> @enderror
                </label>

                <div class="grid gap-5 sm:grid-cols-2">
                    <label class="fieldset">
                        <span class="label">Data</span>
                        <input type="date" class="input w-full" wire:model="performedOn" required>
                        @error('performedOn') <span class="text-error text-sm">{{ $message }}</span> @enderror
                    </label>

                    <label class="fieldset">
                        <span class="label">Local</span>
                        <input
                            type="text"
                            class="input w-full"
                            wire:model="location"
                            list="known-locations"
                            placeholder="Sky"
                        >
                        <datalist id="known-locations">
                            @foreach ($locations as $knownLocation)
                                <option value="{{ $knownLocation }}"></option>
                            @endforeach
                        </datalist>
                        @error('location') <span class="text-error text-sm">{{ $message }}</span> @enderror
                    </label>
                </div>

                <label class="fieldset">
                    <span class="label">Nome do treino livre</span>
                    <input type="text" class="input w-full" wire:model="name" placeholder="Treino livre">
                    @error('name') <span class="text-error text-sm">{{ $message }}</span> @enderror
                </label>

                <div class="card-actions justify-end">
                    <button type="submit" class="btn btn-primary px-8" wire:loading.attr="disabled">
                        Iniciar treino
                    </button>
                </div>
            </div>
        </form>
    @endif
</div>

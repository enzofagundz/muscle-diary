<div>
    <p class="font-display text-xs tracking-[0.25em] text-primary uppercase">Sincronização</p>
    <h1 class="page-title mt-2">Servidor</h1>

    @if ($notice)
        <p class="mt-6 rounded-box bg-base-200 p-5 text-sm" role="status">{{ $notice }}</p>
    @endif

    @if ($failure)
        <p class="mt-6 rounded-box bg-base-200 p-5 text-sm text-error" role="alert">{{ $failure }}</p>
    @endif

    <div class="card mt-6 bg-base-200">
        <div class="card-body gap-4 p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="font-display text-xs tracking-[0.2em] uppercase opacity-60">Pendente de envio</p>
                    <p class="font-display mt-1 text-4xl leading-none">
                        {{ $pending }}
                        <span class="text-base opacity-40">{{ $pending === 1 ? 'registro' : 'registros' }}</span>
                    </p>
                </div>

                <div class="text-right">
                    <p class="font-display text-xs tracking-[0.2em] uppercase opacity-60">Última sincronização</p>
                    <p class="mt-1 text-sm">
                        {{ $settings->last_synced_at?->translatedFormat('d M Y, H:i') ?? 'nunca' }}
                    </p>
                </div>
            </div>

            @if ($settings->isConnected())
                <div class="flex flex-wrap items-center gap-3">
                    <button type="button" class="btn btn-primary px-6" wire:click="sync" wire:loading.attr="disabled">
                        Sincronizar agora
                    </button>

                    <button
                        type="button"
                        class="btn btn-ghost btn-sm text-error"
                        wire:click="disconnect"
                        wire:confirm="Desconectar este aparelho do servidor?"
                    >
                        Desconectar
                    </button>

                    <span class="text-xs opacity-60">{{ $settings->server_url }}</span>
                </div>
            @else
                <form wire:submit="connect" class="flex flex-col gap-4">
                    <label class="fieldset">
                        <span class="label">Endereço do servidor</span>
                        <input type="url" class="input w-full" wire:model="serverUrl" placeholder="https://diario.exemplo.com" required>
                        @error('serverUrl') <span class="text-error text-sm">{{ $message }}</span> @enderror
                    </label>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="fieldset">
                            <span class="label">E-mail</span>
                            <input type="email" class="input w-full" wire:model="email" autocomplete="username" required>
                            @error('email') <span class="text-error text-sm">{{ $message }}</span> @enderror
                        </label>

                        <label class="fieldset">
                            <span class="label">Senha</span>
                            <input type="password" class="input w-full" wire:model="password" autocomplete="current-password" required>
                            @error('password') <span class="text-error text-sm">{{ $message }}</span> @enderror
                        </label>
                    </div>

                    <div class="card-actions justify-end">
                        <button type="submit" class="btn btn-primary px-6" wire:loading.attr="disabled">Conectar</button>
                    </div>
                </form>
            @endif
        </div>
    </div>

    <p class="mt-6 text-xs opacity-50">
        O treino continua funcionando sem servidor. O que for registrado aqui fica pendente até a próxima sincronização.
    </p>
</div>

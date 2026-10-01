<div class="mx-auto flex min-h-[75vh] w-full max-w-md flex-col justify-center">
    <p class="font-display text-xs tracking-[0.25em] text-primary uppercase">Diário de treino</p>
    <h1 class="mt-3 font-display text-6xl leading-[0.85] font-bold uppercase">Entrar</h1>
    <p class="mt-4 text-sm opacity-60">Registre cada série e acompanhe sua progressão.</p>

    <form wire:submit="login" class="mt-10 flex flex-col gap-5">
        <label class="fieldset">
            <span class="label">E-mail</span>
            <input
                type="email"
                class="input w-full"
                wire:model="email"
                autocomplete="username"
                autofocus
                required
            >
            @error('email') <span class="text-error text-sm">{{ $message }}</span> @enderror
        </label>

        <label class="fieldset">
            <span class="label">Senha</span>
            <input
                type="password"
                class="input w-full"
                wire:model="password"
                autocomplete="current-password"
                required
            >
            @error('password') <span class="text-error text-sm">{{ $message }}</span> @enderror
        </label>

        <button type="submit" class="btn btn-primary mt-2" wire:loading.attr="disabled">
            Entrar
        </button>
    </form>
</div>

<div>
    <h1 class="text-2xl font-semibold">{{ config('app.name') }}</h1>
    <p class="mt-1 text-sm opacity-70">Entre para registrar seus treinos.</p>

    <form wire:submit="login" class="mt-6 flex flex-col gap-4">
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

        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
            Entrar
        </button>
    </form>
</div>

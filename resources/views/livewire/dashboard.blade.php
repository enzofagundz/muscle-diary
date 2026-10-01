<div>
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold">{{ config('app.name') }}</h1>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn-ghost btn-sm">Sair</button>
        </form>
    </div>

    <nav class="mt-6 flex gap-2">
        <a href="{{ route('exercises.index') }}" class="btn btn-outline btn-sm" wire:navigate>Exercícios</a>
    </nav>
</div>

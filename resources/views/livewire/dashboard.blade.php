<div>
    <p class="font-display text-xs tracking-[0.25em] text-primary uppercase">Semana atual</p>
    <h1 class="page-title mt-2">Treinos</h1>
    <p class="mt-4 text-sm opacity-60">
        Escolha um modelo para começar a registrar.
    </p>

    <div class="mt-10 flex flex-wrap gap-3">
        <a href="{{ route('templates.index') }}" class="btn btn-primary px-6" wire:navigate>Modelos</a>
        <a href="{{ route('exercises.index') }}" class="btn btn-outline btn-primary px-6" wire:navigate>Exercícios</a>
    </div>
</div>

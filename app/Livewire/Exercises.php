<?php

namespace App\Livewire;

use App\Enums\LoadUnit;
use App\Enums\MuscleGroup;
use App\Models\Exercise;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;

class Exercises extends Component
{
    public string $search = '';

    public string $group = '';

    public bool $showArchived = false;

    public bool $formOpen = false;

    public ?string $editingId = null;

    public string $name = '';

    public string $muscleGroup = '';

    public string $unitDefault = LoadUnit::Kilograms->value;

    public ?string $notes = null;

    public ?string $kgPerPlate = null;

    public ?string $notice = null;

    public function create(): void
    {
        $this->reset('editingId', 'name', 'muscleGroup', 'unitDefault', 'notes', 'kgPerPlate');
        $this->unitDefault = LoadUnit::Kilograms->value;
        $this->formOpen = true;
        $this->notice = null;
        $this->resetValidation();
    }

    public function edit(string $id): void
    {
        $exercise = Exercise::withTrashed()->findOrFail($id);

        if (! $exercise->isGlobal() && $exercise->user_id !== auth()->id()) {
            abort(403);
        }

        if ($exercise->isGlobal()) {
            $exercise = $this->copyToUser($exercise);
            $this->notice = 'Este exercício é do catálogo base, então criamos uma cópia sua para editar. O original fica intacto.';
        } else {
            $this->notice = null;
        }

        $this->editingId = $exercise->id;
        $this->name = $exercise->name;
        $this->muscleGroup = $exercise->muscle_group;
        $this->unitDefault = $exercise->unit_default->value;
        $this->notes = $exercise->notes;
        $this->kgPerPlate = $exercise->kg_per_plate === null
            ? null
            : rtrim(rtrim($exercise->kg_per_plate, '0'), '.');
        $this->formOpen = true;
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->kgPerPlate = $this->kgPerPlate === null
            ? null
            : str_replace(',', '.', $this->kgPerPlate);

        $validated = $this->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('exercises', 'name')
                    ->where('user_id', auth()->id())
                    ->whereNull('deleted_at')
                    ->ignore($this->editingId),
            ],
            'muscleGroup' => ['required', Rule::in(MuscleGroup::values())],
            'unitDefault' => ['required', Rule::enum(LoadUnit::class)],
            'notes' => ['nullable', 'string', 'max:1000'],
            'kgPerPlate' => ['nullable', 'numeric', 'min:0', 'max:999'],
        ], attributes: ['kgPerPlate' => 'peso da placa']);

        $attributes = [
            'name' => trim($validated['name']),
            'muscle_group' => $validated['muscleGroup'],
            'unit_default' => $validated['unitDefault'],
            'notes' => $validated['notes'],
            'kg_per_plate' => $validated['kgPerPlate'] === null || $validated['kgPerPlate'] === ''
                ? null
                : (float) $validated['kgPerPlate'],
        ];

        if ($this->editingId === null) {
            $attributes['based_on_id'] = $this->catalogIdForName($attributes['name']);

            auth()->user()->exercises()->create($attributes);
        } else {
            $this->ownedExercise($this->editingId)->update($attributes);
        }

        $this->formOpen = false;
        $this->reset('editingId', 'name', 'muscleGroup', 'unitDefault', 'notes', 'kgPerPlate');
    }

    public function cancel(): void
    {
        $this->formOpen = false;
        $this->notice = null;
        $this->reset('editingId', 'name', 'muscleGroup', 'unitDefault', 'notes', 'kgPerPlate');
        $this->resetValidation();
    }

    public function archive(string $id): void
    {
        $this->ownedExercise($id)->delete();
    }

    public function restore(string $id): void
    {
        Exercise::withTrashed()
            ->where('user_id', auth()->id())
            ->findOrFail($id)
            ->restore();
    }

    public function render(): View
    {
        $user = auth()->user();

        $exercises = $this->showArchived
            ? Exercise::onlyTrashed()->where('user_id', $user->id)->orderBy('name')->get()
            : Exercise::query()
                ->visibleTo($user)
                ->when($this->search !== '', fn ($query) => $query->where('name', 'like', '%'.$this->search.'%'))
                ->when($this->group !== '', fn ($query) => $query->where('muscle_group', $this->group))
                ->orderBy('muscle_group')
                ->orderBy('name')
                ->get();

        return view('livewire.exercises', [
            'exercises' => $exercises,
            'groups' => MuscleGroup::values(),
            'units' => LoadUnit::cases(),
            'archivedCount' => Exercise::onlyTrashed()->where('user_id', $user->id)->count(),
        ]);
    }

    private function ownedExercise(string $id): Exercise
    {
        return Exercise::query()
            ->where('user_id', auth()->id())
            ->findOrFail($id);
    }

    private function copyToUser(Exercise $exercise): Exercise
    {
        return auth()->user()->exercises()->create([
            'based_on_id' => $exercise->id,
            'name' => $exercise->name,
            'muscle_group' => $exercise->muscle_group,
            'unit_default' => $exercise->unit_default->value,
            'kg_per_plate' => $exercise->kg_per_plate,
            'notes' => $exercise->notes,
        ]);
    }

    private function catalogIdForName(string $name): ?string
    {
        return Exercise::query()
            ->whereNull('user_id')
            ->where('name', $name)
            ->value('id');
    }
}

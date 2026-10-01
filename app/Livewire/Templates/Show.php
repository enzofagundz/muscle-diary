<?php

namespace App\Livewire\Templates;

use App\Models\Exercise;
use App\Models\TemplateItem;
use App\Models\WorkoutTemplate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;

class Show extends Component
{
    public WorkoutTemplate $template;

    public bool $formOpen = false;

    public ?string $editingId = null;

    public string $exerciseId = '';

    public int $sets = 3;

    public ?int $repMin = null;

    public ?int $repMax = null;

    public ?int $restSeconds = null;

    public ?string $notes = null;

    public function mount(WorkoutTemplate $template): void
    {
        $this->template = $template;

        $this->authorizeTemplate();
    }

    public function hydrate(): void
    {
        $this->authorizeTemplate();
    }

    public function addItem(): void
    {
        $this->reset('editingId', 'exerciseId', 'repMin', 'repMax', 'restSeconds', 'notes');
        $this->sets = 3;
        $this->formOpen = true;
        $this->resetValidation();
    }

    public function editItem(string $id): void
    {
        $item = $this->ownedItem($id);

        $this->editingId = $item->id;
        $this->exerciseId = $item->exercise_id;
        $this->sets = $item->sets;
        $this->repMin = $item->rep_min;
        $this->repMax = $item->rep_max;
        $this->restSeconds = $item->rest_seconds;
        $this->notes = $item->notes;
        $this->formOpen = true;
        $this->resetValidation();
    }

    public function saveItem(): void
    {
        $validated = $this->validate([
            'exerciseId' => [
                'required',
                Rule::exists('exercises', 'id')->where(function ($query): void {
                    $query->where(function ($query): void {
                        $query->where('user_id', auth()->id())
                            ->orWhereNull('user_id');
                    })->whereNull('deleted_at');
                }),
            ],
            'sets' => ['required', 'integer', 'min:1', 'max:20'],
            'repMin' => ['nullable', 'integer', 'min:1', 'max:100'],
            'repMax' => ['nullable', 'integer', 'min:1', 'max:100', 'gte:repMin'],
            'restSeconds' => ['nullable', 'integer', 'min:0', 'max:3600'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], attributes: [
            'exerciseId' => 'exercício',
            'sets' => 'séries',
            'repMin' => 'repetições mínimas',
            'repMax' => 'repetições máximas',
            'restSeconds' => 'descanso',
        ]);

        $attributes = [
            'exercise_id' => $validated['exerciseId'],
            'sets' => $validated['sets'],
            'rep_min' => $validated['repMin'],
            'rep_max' => $validated['repMax'],
            'rest_seconds' => $validated['restSeconds'],
            'notes' => $validated['notes'],
        ];

        if ($this->editingId === null) {
            $attributes['user_id'] = auth()->id();
            $attributes['position'] = (int) $this->template->items()->max('position') + 1;

            $this->template->items()->create($attributes);
        } else {
            $this->ownedItem($this->editingId)->update($attributes);
        }

        $this->cancelItem();
    }

    public function cancelItem(): void
    {
        $this->formOpen = false;
        $this->reset('editingId', 'exerciseId', 'repMin', 'repMax', 'restSeconds', 'notes');
        $this->sets = 3;
        $this->resetValidation();
    }

    public function removeItem(string $id): void
    {
        $this->ownedItem($id)->delete();
    }

    public function moveUp(string $id): void
    {
        $this->move($id, direction: -1);
    }

    public function moveDown(string $id): void
    {
        $this->move($id, direction: 1);
    }

    public function render(): View
    {
        return view('livewire.templates.show', [
            'items' => $this->template->items()->with('exercise')->get(),
            'exercises' => Exercise::query()
                ->visibleTo(auth()->user())
                ->orderBy('muscle_group')
                ->orderBy('name')
                ->get(),
        ]);
    }

    private function move(string $id, int $direction): void
    {
        $item = $this->ownedItem($id);

        $neighbour = $this->template->items()
            ->whereKeyNot($item->id)
            ->where('position', $direction < 0 ? '<' : '>', $item->position)
            ->orderBy('position', $direction < 0 ? 'desc' : 'asc')
            ->first();

        if ($neighbour === null) {
            return;
        }

        $position = $item->position;

        $item->update(['position' => $neighbour->position]);
        $neighbour->update(['position' => $position]);
    }

    private function authorizeTemplate(): void
    {
        abort_unless($this->template->user_id === auth()->id(), 404);
    }

    private function ownedItem(string $id): TemplateItem
    {
        return $this->template->items()->findOrFail($id);
    }
}

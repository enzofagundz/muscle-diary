<?php

namespace App\Livewire\Templates;

use App\Models\WorkoutTemplate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;

class Index extends Component
{
    public bool $formOpen = false;

    public ?string $editingId = null;

    public string $name = '';

    public int $restSeconds = 90;

    public ?string $notes = null;

    public function create(): void
    {
        $this->reset('editingId', 'name', 'notes');
        $this->restSeconds = 90;
        $this->formOpen = true;
        $this->resetValidation();
    }

    public function edit(string $id): void
    {
        $template = $this->ownedTemplate($id);

        $this->editingId = $template->id;
        $this->name = $template->name;
        $this->restSeconds = $template->rest_seconds;
        $this->notes = $template->notes;
        $this->formOpen = true;
        $this->resetValidation();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('workout_templates', 'name')
                    ->where('user_id', auth()->id())
                    ->whereNull('deleted_at')
                    ->ignore($this->editingId),
            ],
            'restSeconds' => ['required', 'integer', 'min:0', 'max:3600'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $attributes = [
            'name' => trim($validated['name']),
            'rest_seconds' => $validated['restSeconds'],
            'notes' => $validated['notes'],
        ];

        if ($this->editingId === null) {
            $attributes['position'] = $this->nextPosition();

            auth()->user()->workoutTemplates()->create($attributes);
        } else {
            $this->ownedTemplate($this->editingId)->update($attributes);
        }

        $this->cancel();
    }

    public function cancel(): void
    {
        $this->formOpen = false;
        $this->reset('editingId', 'name', 'notes');
        $this->restSeconds = 90;
        $this->resetValidation();
    }

    public function duplicate(string $id): void
    {
        $template = $this->ownedTemplate($id);

        $copy = auth()->user()->workoutTemplates()->create([
            'name' => $template->name.' (cópia)',
            'rest_seconds' => $template->rest_seconds,
            'notes' => $template->notes,
            'position' => $this->nextPosition(),
            'is_active' => $template->is_active,
        ]);

        foreach ($template->items as $item) {
            $copy->items()->create([
                'user_id' => auth()->id(),
                'exercise_id' => $item->exercise_id,
                'position' => $item->position,
                'sets' => $item->sets,
                'rep_min' => $item->rep_min,
                'rep_max' => $item->rep_max,
                'rest_seconds' => $item->rest_seconds,
                'notes' => $item->notes,
            ]);
        }
    }

    public function toggleActive(string $id): void
    {
        $template = $this->ownedTemplate($id);

        $template->update(['is_active' => ! $template->is_active]);
    }

    public function delete(string $id): void
    {
        $template = $this->ownedTemplate($id);

        $template->items()->get()->each->delete();
        $template->delete();
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
        return view('livewire.templates.index', [
            'templates' => auth()->user()->workoutTemplates()
                ->withCount('items')
                ->orderBy('position')
                ->orderBy('name')
                ->get(),
        ]);
    }

    private function move(string $id, int $direction): void
    {
        $template = $this->ownedTemplate($id);

        $neighbour = WorkoutTemplate::query()
            ->where('user_id', auth()->id())
            ->whereKeyNot($template->id)
            ->where('position', $direction < 0 ? '<' : '>', $template->position)
            ->orderBy('position', $direction < 0 ? 'desc' : 'asc')
            ->first();

        if ($neighbour === null) {
            return;
        }

        $position = $template->position;

        $template->update(['position' => $neighbour->position]);
        $neighbour->update(['position' => $position]);
    }

    private function nextPosition(): int
    {
        return (int) auth()->user()->workoutTemplates()->max('position') + 1;
    }

    private function ownedTemplate(string $id): WorkoutTemplate
    {
        return WorkoutTemplate::query()
            ->where('user_id', auth()->id())
            ->findOrFail($id);
    }
}

<?php

namespace App\Livewire;

use App\Models\WorkoutTemplate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;

class Dashboard extends Component
{
    public string $templateId = '';

    public string $name = '';

    public string $location = '';

    public string $performedOn = '';

    public function mount(): void
    {
        $this->performedOn = now()->toDateString();
        $this->templateId = WorkoutTemplate::nextInRotationFor(auth()->user())?->id ?? '';
    }

    public function start(): void
    {
        $user = auth()->user();

        $validated = $this->validate([
            'templateId' => [
                'nullable',
                Rule::exists('workout_templates', 'id')
                    ->where('user_id', $user->id)
                    ->whereNull('deleted_at'),
            ],
            'name' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'performedOn' => ['required', 'date'],
        ]);

        $template = $validated['templateId'] === null || $validated['templateId'] === ''
            ? null
            : $user->workoutTemplates()->find($validated['templateId']);

        $session = $user->workoutSessions()->create([
            'workout_template_id' => $template?->id,
            'name' => $template?->name ?? ($validated['name'] ?: 'Treino livre'),
            'performed_on' => $validated['performedOn'],
            'location' => $validated['location'] ?: null,
            'rest_seconds' => $template?->rest_seconds ?? 90,
        ]);

        foreach ($template?->items ?? [] as $item) {
            $session->items()->create([
                'user_id' => $user->id,
                'exercise_id' => $item->exercise_id,
                'position' => $item->position,
                'planned_sets' => $item->sets,
                'rep_min' => $item->rep_min,
                'rep_max' => $item->rep_max,
                'rest_seconds' => $item->rest_seconds,
                'notes' => $item->notes,
            ]);
        }

        $this->redirectRoute('sessions.run', $session, navigate: true);
    }

    public function render(): View
    {
        $user = auth()->user();

        return view('livewire.dashboard', [
            'templates' => $user->workoutTemplates()
                ->where('is_active', true)
                ->orderBy('position')
                ->orderBy('name')
                ->get(),
            'next' => WorkoutTemplate::nextInRotationFor($user),
            'inProgress' => $user->workoutSessions()
                ->whereNull('finished_at')
                ->latest('created_at')
                ->first(),
            'locations' => $user->workoutSessions()
                ->whereNotNull('location')
                ->distinct()
                ->orderBy('location')
                ->pluck('location'),
        ]);
    }
}

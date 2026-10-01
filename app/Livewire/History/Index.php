<?php

namespace App\Livewire\History;

use App\Enums\MuscleGroup;
use App\Models\Exercise;
use App\Models\WorkoutSession;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;

class Index extends Component
{
    public string $templateId = '';

    public string $exerciseId = '';

    public string $muscleGroup = '';

    public string $location = '';

    public function render(): View
    {
        $user = auth()->user();

        $finished = $user->workoutSessions()
            ->whereNotNull('finished_at')
            ->when($this->templateId !== '', fn ($query) => $query->where('workout_template_id', $this->templateId))
            ->when($this->exerciseId !== '', fn ($query) => $query->whereHas(
                'items',
                fn ($query) => $query->where('exercise_id', $this->exerciseId),
            ))
            ->when($this->muscleGroup !== '', fn ($query) => $query->whereHas(
                'items.exercise',
                fn ($query) => $query->where('muscle_group', $this->muscleGroup),
            ))
            ->when($this->location !== '', fn ($query) => $query->where('location', $this->location))
            ->orderByDesc('performed_on')
            ->orderByDesc('finished_at')
            ->get();

        return view('livewire.history.index', [
            'weeks' => $this->weeks($finished),
            'templates' => $user->workoutTemplates()->orderBy('name')->get(),
            'exercises' => Exercise::query()->visibleTo($user)->orderBy('name')->get(),
            'groups' => MuscleGroup::values(),
            'locations' => $user->workoutSessions()->whereNotNull('location')->distinct()->orderBy('location')->pluck('location'),
            'unfinished' => $user->workoutSessions()
                ->whereNull('finished_at')
                ->orderByDesc('performed_on')
                ->get(),
        ]);
    }

    /**
     * Every week between the oldest recorded workout and today, newest first,
     * so weeks without training stay visible.
     *
     * @param  Collection<int, WorkoutSession>  $sessions
     * @return array<int, array{start: CarbonInterface, end: CarbonInterface, sessions: Collection<int, WorkoutSession>}>
     */
    private function weeks(Collection $sessions): array
    {
        if ($sessions->isEmpty()) {
            return [];
        }

        $oldest = $sessions->last()->performed_on->copy()->startOfWeek(CarbonInterface::MONDAY);
        $weeks = [];

        for ($start = now()->startOfWeek(CarbonInterface::MONDAY); $start->greaterThanOrEqualTo($oldest); $start = $start->copy()->subWeek()) {
            $end = $start->copy()->endOfWeek(CarbonInterface::SUNDAY);

            $weeks[] = [
                'start' => $start->copy(),
                'end' => $end,
                'sessions' => $sessions
                    ->filter(fn (WorkoutSession $session): bool => $session->performed_on->betweenIncluded($start, $end))
                    ->values(),
            ];
        }

        return $weeks;
    }
}

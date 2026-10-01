<?php

namespace App\Livewire\History;

use App\Enums\MuscleGroup;
use App\Models\Exercise;
use App\Models\SessionItem;
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
            ->with(['items.exercise', 'items.sets'])
            ->orderByDesc('performed_on')
            ->orderByDesc('finished_at')
            ->get();

        return view('livewire.history.index', [
            'weeks' => $this->weeks($finished),
            'groups' => $this->groups($finished),
            'templates' => $user->workoutTemplates()->orderBy('name')->get(),
            'exercises' => Exercise::query()->visibleTo($user)->orderBy('name')->get(),
            'muscleGroups' => MuscleGroup::values(),
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

            $weekSessions = $sessions
                ->filter(fn (WorkoutSession $session): bool => $session->performed_on->betweenIncluded($start, $end))
                ->values();

            $weeks[] = [
                'start' => $start->copy(),
                'end' => $end,
                'sessions' => $weekSessions,
                'volume' => round($weekSessions->sum(fn (WorkoutSession $session): float => $session->volumeInKg()), 2),
            ];
        }

        return $weeks;
    }

    /**
     * How each muscle group is going: how much volume it accumulated and how
     * long it has been since the last time it was trained.
     *
     * @param  Collection<int, WorkoutSession>  $sessions
     * @return Collection<int, array{name: string, volumeInKg: float, sets: int, daysSince: int|null}>
     */
    private function groups(Collection $sessions): Collection
    {
        return $sessions
            ->flatMap(fn (WorkoutSession $session) => $session->items->map(
                fn (SessionItem $item): array => ['item' => $item, 'session' => $session],
            ))
            ->groupBy(fn (array $row): string => $row['item']->exercise->muscle_group)
            ->map(function (Collection $rows, string $group): array {
                $volumes = $rows->map(fn (array $row): ?array => $row['item']->volume());

                return [
                    'name' => $group,
                    'volumeInKg' => round($volumes
                        ->filter(fn (?array $volume): bool => $volume !== null && $volume['unit'] === 'kg')
                        ->sum(fn (array $volume): float => $volume['value']), 2),
                    'sets' => $rows->sum(fn (array $row): int => $row['item']->sets->where('is_warmup', false)->count()),
                    'daysSince' => (int) $rows->max(fn (array $row) => $row['session']->performed_on)->diffInDays(now()),
                ];
            })
            ->sortBy('name')
            ->values();
    }
}

<?php

namespace App\Livewire\Exercises;

use App\Models\Exercise;
use App\Models\SessionItem;
use App\Models\SessionSet;
use App\Models\WorkoutSession;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;

class Show extends Component
{
    public Exercise $exercise;

    public function mount(Exercise $exercise): void
    {
        $this->exercise = $exercise;

        $this->authorizeExercise();
    }

    public function hydrate(): void
    {
        $this->authorizeExercise();
    }

    public function render(): View
    {
        $executions = $this->executions();

        return view('livewire.exercises.show', [
            'executions' => $executions,
            'best' => $this->best($executions),
            'chart' => $this->chart($executions),
            'volumeChart' => $this->volumeChart($executions),
        ]);
    }

    /**
     * Every time the exercise was performed, oldest first, with the sets of
     * each execution and the workout it happened in.
     *
     * @return Collection<int, array{item: SessionItem, sets: Collection<int, SessionSet>, session: WorkoutSession}>
     */
    private function executions(): Collection
    {
        return SessionItem::query()
            ->where('user_id', auth()->id())
            ->where('exercise_id', $this->exercise->id)
            ->with(['exercise', 'sets', 'session'])
            ->get()
            ->sortBy(fn (SessionItem $item): array => [
                $item->session->performed_on->timestamp,
                $item->session->created_at->timestamp,
            ])
            ->values()
            ->map(fn (SessionItem $item): array => [
                'item' => $item,
                'session' => $item->session,
                'sets' => $item->sets->where('is_warmup', false)->values(),
            ]);
    }

    /**
     * Best performance ever recorded: the heaviest set, reps breaking the tie.
     * Warmup sets are not counted.
     *
     * @param  Collection<int, array{item: SessionItem, sets: Collection<int, SessionSet>, session: WorkoutSession}>  $executions
     * @return array{load: float, unit: string, reps: int|null, date: string}|null
     */
    private function best(Collection $executions): ?array
    {
        $best = $executions
            ->flatMap(fn (array $execution) => $execution['sets']->map(
                fn ($set): array => ['set' => $set, 'date' => $execution['session']->performed_on],
            ))
            ->filter(fn (array $row): bool => $row['set']->load !== null)
            ->sortByDesc(fn (array $row): array => [(float) $row['set']->load, $row['set']->reps ?? 0])
            ->first();

        if ($best === null) {
            return null;
        }

        return [
            'load' => (float) $best['set']->load,
            'unit' => $best['set']->unit->label(),
            'reps' => $best['set']->reps,
            'date' => $best['date']->toDateString(),
        ];
    }

    /**
     * Load of the best set of each execution, in the exercise's own unit, so
     * the line never mixes plates with kilograms.
     *
     * @param  Collection<int, array{item: SessionItem, sets: Collection<int, SessionSet>, session: WorkoutSession}>  $executions
     * @return array{points: array<int, array{load: float, date: string}>, polyline: string, unit: string}
     */
    private function chart(Collection $executions): array
    {
        $points = $executions
            ->map(function (array $execution): ?array {
                $set = $execution['sets']
                    ->filter(fn ($set): bool => $set->load !== null && $set->unit === $this->exercise->unit_default)
                    ->sortByDesc(fn ($set): array => [(float) $set->load, $set->reps ?? 0])
                    ->first();

                return $set === null ? null : [
                    'load' => (float) $set->load,
                    'date' => $execution['session']->performed_on->toDateString(),
                ];
            })
            ->filter()
            ->values();

        return [
            'points' => $points->all(),
            'polyline' => $this->polyline($points->pluck('load')),
            'unit' => $this->exercise->unit_default->label(),
        ];
    }

    /**
     * Volume of each execution, in kilograms, which is what makes workouts of
     * different exercises comparable.
     *
     * @param  Collection<int, array{item: SessionItem, sets: Collection<int, SessionSet>, session: WorkoutSession}>  $executions
     * @return array{points: array<int, array{volume: float, date: string}>, polyline: string, unit: string}
     */
    private function volumeChart(Collection $executions): array
    {
        $points = $executions
            ->map(function (array $execution): ?array {
                $volume = $execution['item']->volume();

                return $volume === null || $volume['unit'] !== 'kg' ? null : [
                    'volume' => $volume['value'],
                    'date' => $execution['session']->performed_on->toDateString(),
                ];
            })
            ->filter()
            ->values();

        return [
            'points' => $points->all(),
            'polyline' => $this->polyline($points->pluck('volume')),
            'unit' => 'kg',
        ];
    }

    /**
     * @param  Collection<int, float>  $values
     */
    private function polyline(Collection $values): string
    {
        $values = $values->values();

        if ($values->isEmpty()) {
            return '';
        }

        $min = $values->min();
        $max = $values->max();
        $span = $max - $min;
        $lastIndex = max($values->count() - 1, 1);

        return $values
            ->map(function (float $value, int $index) use ($min, $span, $lastIndex): string {
                $x = round($index / $lastIndex * 100, 2);
                $y = $span === 0.0 ? 20 : round(38 - (($value - $min) / $span) * 36, 2);

                return $x.','.$y;
            })
            ->implode(' ');
    }

    private function authorizeExercise(): void
    {
        abort_unless(
            $this->exercise->user_id === null || $this->exercise->user_id === auth()->id(),
            404,
        );
    }
}

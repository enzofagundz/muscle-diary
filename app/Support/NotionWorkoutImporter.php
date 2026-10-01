<?php

namespace App\Support;

use App\Enums\LoadUnit;
use App\Models\Exercise;
use App\Models\User;
use App\Models\WorkoutSession;
use Illuminate\Support\Str;

/**
 * Reads the free-text workout notes and turns them into structured workouts.
 *
 * The format is one `## date — name — location` heading per workout, one
 * `### exercise — muscle group` heading per exercise, one line per set, and
 * `obs:` for notes. Anything that cannot be read with confidence is reported
 * instead of guessed.
 */
class NotionWorkoutImporter
{
    public function __construct(private readonly User $user) {}

    public function import(string $file): ImportReport
    {
        $report = new ImportReport;

        foreach ($this->blocks($file) as $block) {
            $this->importBlock($block, $report);
        }

        return $report;
    }

    /**
     * @return list<array{heading: string, lines: list<string>}>
     */
    private function blocks(string $file): array
    {
        $blocks = [];
        $current = null;

        foreach (explode("\n", (string) file_get_contents($file)) as $line) {
            $line = rtrim($line);

            if (str_starts_with($line, '## ')) {
                if ($current !== null) {
                    $blocks[] = $current;
                }

                $current = ['heading' => trim(substr($line, 3)), 'lines' => []];

                continue;
            }

            if ($current !== null && trim($line) !== '') {
                $current['lines'][] = $line;
            }
        }

        if ($current !== null) {
            $blocks[] = $current;
        }

        return $blocks;
    }

    /**
     * @param  array{heading: string, lines: list<string>}  $block
     */
    private function importBlock(array $block, ImportReport $report): void
    {
        $parts = array_map('trim', explode('—', $block['heading']));

        $date = $parts[0] ?? '';

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $report->problems[] = "Cabeçalho sem data legível: {$block['heading']}";

            return;
        }

        $name = $parts[1] ?? 'Treino importado';
        $location = $parts[2] ?? null;

        if (WorkoutSession::query()
            ->where('user_id', $this->user->id)
            ->whereDate('performed_on', $date)
            ->where('name', $name)
            ->exists()) {
            $report->skipped[] = "{$date} {$name} já estava importado";

            return;
        }

        $session = $this->user->workoutSessions()->create([
            'name' => $name,
            'performed_on' => $date,
            'location' => $location,
            'finished_at' => $date.' 12:00:00',
        ]);

        foreach ($this->exerciseBlocks($block['lines']) as $position => $exerciseBlock) {
            $this->importExercise($session, $exerciseBlock, $position + 1, $report);
        }

        $report->created[] = "{$date} {$name}";
    }

    /**
     * @param  list<string>  $lines
     * @return list<array{heading: string, lines: list<string>}>
     */
    private function exerciseBlocks(array $lines): array
    {
        $blocks = [];
        $current = null;

        foreach ($lines as $line) {
            if (str_starts_with($line, '### ')) {
                if ($current !== null) {
                    $blocks[] = $current;
                }

                $current = ['heading' => trim(substr($line, 4)), 'lines' => []];

                continue;
            }

            if ($current !== null) {
                $current['lines'][] = trim($line);
            }
        }

        if ($current !== null) {
            $blocks[] = $current;
        }

        return $blocks;
    }

    /**
     * @param  array{heading: string, lines: list<string>}  $block
     */
    private function importExercise(WorkoutSession $session, array $block, int $position, ImportReport $report): void
    {
        $parts = array_map('trim', explode('—', $block['heading']));
        $name = $parts[0] ?? '';
        $group = $parts[1] ?? null;

        $exercise = $this->resolveExercise($name, $group);

        if ($exercise === null) {
            $report->problems[] = "Exercício sem grupo muscular para criar: {$name}";

            return;
        }

        $notes = null;
        $sets = [];

        foreach ($block['lines'] as $line) {
            if (Str::startsWith(Str::lower($line), 'obs:')) {
                $notes = trim(substr($line, 4));

                continue;
            }

            $segments = $this->parseSet($line);

            if ($segments === null) {
                $report->problems[] = "Série ilegível em {$name}: {$line}";

                continue;
            }

            $sets[] = $segments;
        }

        if ($sets === []) {
            return;
        }

        $item = $session->items()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'position' => $position,
            'planned_sets' => count($sets),
            'notes' => $notes,
        ]);

        foreach ($sets as $setNumber => $segments) {
            foreach ($segments as $part => $segment) {
                $item->sets()->create([
                    'user_id' => $this->user->id,
                    'set_number' => $setNumber + 1,
                    'part' => $part,
                    'load' => $segment['load'],
                    'unit' => $segment['unit'],
                    'reps' => $segment['reps'],
                ]);
            }
        }
    }

    private function resolveExercise(string $name, ?string $group): ?Exercise
    {
        $existing = Exercise::query()
            ->visibleTo($this->user)
            ->whereRaw('lower(name) = ?', [Str::lower($name)])
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        if ($group === null || $group === '') {
            return null;
        }

        return Exercise::query()->create([
            'user_id' => $this->user->id,
            'name' => $name,
            'muscle_group' => $group,
            'unit_default' => LoadUnit::Kilograms,
        ]);
    }

    /**
     * One set may hold several segments, as in `40 kg — 4 + 35 kg — 4`.
     *
     * @return list<array{load: float|null, unit: string, reps: int|null}>|null
     */
    private function parseSet(string $line): ?array
    {
        $segments = [];

        foreach (explode('+', $line) as $segment) {
            $parsed = $this->parseSegment($segment);

            if ($parsed === null) {
                return null;
            }

            $segments[] = $parsed;
        }

        return $segments === [] ? null : $segments;
    }

    /**
     * @return array{load: float|null, unit: string, reps: int|null}|null
     */
    private function parseSegment(string $segment): ?array
    {
        $segment = trim($segment);

        $matched = preg_match(
            '/^(?:(?<load>\d+(?:[.,]\d+)?)\s*(?<unit>kg|kgs|placas?|pl|libras?|lb)?\s*)?(?:[—–\-x×]\s*)?(?<reps>\d+)?$/iu',
            $segment,
            $matches,
        );

        if ($matched !== 1) {
            return null;
        }

        $load = ($matches['load'] ?? '') === '' ? null : (float) str_replace(',', '.', $matches['load']);
        $reps = ($matches['reps'] ?? '') === '' ? null : (int) $matches['reps'];

        if ($load === null && $reps === null) {
            return null;
        }

        return [
            'load' => $load,
            'unit' => $this->unit($matches['unit'] ?? '', $load)->value,
            'reps' => $reps,
        ];
    }

    private function unit(string $unit, ?float $load): LoadUnit
    {
        $unit = Str::lower(trim($unit));

        return match (true) {
            in_array($unit, ['pl', 'placa', 'placas'], true) => LoadUnit::Plates,
            in_array($unit, ['lb', 'libra', 'libras'], true) => LoadUnit::Pounds,
            in_array($unit, ['kg', 'kgs'], true) => LoadUnit::Kilograms,
            $load === null => LoadUnit::Bodyweight,
            default => LoadUnit::Kilograms,
        };
    }
}

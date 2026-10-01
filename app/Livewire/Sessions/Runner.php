<?php

namespace App\Livewire\Sessions;

use App\Enums\LoadUnit;
use App\Models\Exercise;
use App\Models\SessionItem;
use App\Models\SessionSet;
use App\Models\WorkoutSession;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;

class Runner extends Component
{
    public WorkoutSession $session;

    /** @var array<string, array{load: float|null, unit: string, reps: int|null, is_warmup: bool, notes: string|null}> */
    public array $setDrafts = [];

    /** @var array<string, string|null> */
    public array $itemNotes = [];

    public string $sessionNotes = '';

    public bool $formOpen = false;

    public string $exerciseId = '';

    public int $plannedSets = 3;

    public ?int $repMin = null;

    public ?int $repMax = null;

    public function mount(WorkoutSession $session): void
    {
        $this->session = $session;

        $this->authorizeSession();
        $this->fillDrafts();
    }

    public function hydrate(): void
    {
        $this->authorizeSession();
    }

    public function addSet(string $itemId): void
    {
        $item = $this->ownedItem($itemId);

        $item->sets()->create([
            'user_id' => auth()->id(),
            'set_number' => (int) $item->sets()->max('set_number') + 1,
            'part' => 0,
            'unit' => $item->exercise->unit_default->value,
        ]);

        $this->fillDrafts();
    }

    public function removeSet(string $setId): void
    {
        $this->ownedSet($setId)->delete();

        $this->fillDrafts();
    }

    public function copyPrevious(string $setId): void
    {
        $set = $this->ownedSet($setId);

        $previous = SessionSet::query()
            ->where('session_item_id', $set->session_item_id)
            ->where('set_number', '<', $set->set_number)
            ->orderByDesc('set_number')
            ->orderByDesc('part')
            ->first();

        if ($previous === null) {
            return;
        }

        $set->update([
            'load' => $previous->load,
            'unit' => $previous->unit,
            'reps' => $previous->reps,
        ]);

        $this->fillDrafts();
    }

    public function updatedSetDrafts(mixed $value, string $key): void
    {
        [$setId, $field] = array_pad(explode('.', $key, 2), 2, null);

        if ($field === null) {
            return;
        }

        $set = $this->ownedSet($setId);

        match ($field) {
            'load' => $this->saveLoad($set, $value, $key),
            'unit' => $set->update(['unit' => $value]),
            'reps' => $this->saveReps($set, $value, $key),
            'is_warmup' => $set->update(['is_warmup' => (bool) $value]),
            'notes' => $set->update(['notes' => $this->blankToNull($value)]),
            default => null,
        };
    }

    public function updatedItemNotes(mixed $value, string $itemId): void
    {
        $this->ownedItem($itemId)->update(['notes' => $this->blankToNull($value)]);
    }

    public function updatedSessionNotes(mixed $value): void
    {
        $this->session->update(['notes' => $this->blankToNull($value)]);
    }

    public function addItem(): void
    {
        $this->reset('exerciseId', 'repMin', 'repMax');
        $this->plannedSets = 3;
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
            'plannedSets' => ['required', 'integer', 'min:1', 'max:20'],
            'repMin' => ['nullable', 'integer', 'min:1', 'max:100'],
            'repMax' => ['nullable', 'integer', 'min:1', 'max:100', 'gte:repMin'],
        ], attributes: [
            'exerciseId' => 'exercício',
            'plannedSets' => 'séries',
            'repMin' => 'repetições mínimas',
            'repMax' => 'repetições máximas',
        ]);

        $this->session->items()->create([
            'user_id' => auth()->id(),
            'exercise_id' => $validated['exerciseId'],
            'position' => (int) $this->session->items()->max('position') + 1,
            'planned_sets' => $validated['plannedSets'],
            'rep_min' => $validated['repMin'],
            'rep_max' => $validated['repMax'],
        ]);

        $this->cancelItem();
    }

    public function cancelItem(): void
    {
        $this->formOpen = false;
        $this->reset('exerciseId', 'repMin', 'repMax');
        $this->plannedSets = 3;
        $this->resetValidation();
    }

    public function removeItem(string $itemId): void
    {
        $item = $this->ownedItem($itemId);

        $item->sets()->delete();
        $item->delete();
    }

    public function render(): View
    {
        return view('livewire.sessions.runner', [
            'items' => $this->session->items()->with(['exercise', 'sets'])->get(),
            'exercises' => Exercise::query()
                ->visibleTo(auth()->user())
                ->orderBy('muscle_group')
                ->orderBy('name')
                ->get(),
            'units' => LoadUnit::cases(),
        ]);
    }

    private function fillDrafts(): void
    {
        $this->setDrafts = [];
        $this->itemNotes = [];
        $this->sessionNotes = $this->session->notes ?? '';

        foreach ($this->session->items()->with('sets')->get() as $item) {
            $this->itemNotes[$item->id] = $item->notes;

            foreach ($item->sets as $set) {
                $this->setDrafts[$set->id] = [
                    'load' => $set->load,
                    'unit' => $set->unit->value,
                    'reps' => $set->reps,
                    'is_warmup' => $set->is_warmup,
                    'notes' => $set->notes,
                ];
            }
        }
    }

    private function saveLoad(SessionSet $set, mixed $value, string $key): void
    {
        $normalised = $this->normaliseNumber($value);

        if ($normalised === null && $this->blankToNull($value) !== null) {
            $this->addError('setDrafts.'.$key, 'Carga precisa ser um número.');

            return;
        }

        $set->update(['load' => $normalised]);
    }

    private function saveReps(SessionSet $set, mixed $value, string $key): void
    {
        if ($this->blankToNull($value) === null) {
            $set->update(['reps' => null]);

            return;
        }

        if (! is_numeric($value) || (int) $value < 0) {
            $this->addError('setDrafts.'.$key, 'Repetições precisam ser um número inteiro.');

            return;
        }

        $set->update(['reps' => (int) $value]);
    }

    private function normaliseNumber(mixed $value): ?float
    {
        $value = $this->blankToNull($value);

        if ($value === null) {
            return null;
        }

        $value = str_replace(',', '.', (string) $value);

        return is_numeric($value) ? (float) $value : null;
    }

    private function blankToNull(mixed $value): mixed
    {
        return $value === '' || $value === null ? null : $value;
    }

    private function authorizeSession(): void
    {
        abort_unless($this->session->user_id === auth()->id(), 404);
    }

    private function ownedItem(string $id): SessionItem
    {
        return $this->session->items()->findOrFail($id);
    }

    private function ownedSet(string $id): SessionSet
    {
        return SessionSet::query()
            ->whereIn('session_item_id', $this->session->items()->select('id'))
            ->findOrFail($id);
    }
}

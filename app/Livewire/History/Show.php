<?php

namespace App\Livewire\History;

use App\Models\SessionItem;
use App\Models\SessionSet;
use App\Models\WorkoutSession;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;

class Show extends Component
{
    public WorkoutSession $session;

    public bool $formOpen = false;

    public string $name = '';

    public string $performedOn = '';

    public string $location = '';

    public string $notes = '';

    public string $compareWithId = '';

    public function mount(WorkoutSession $session): void
    {
        $this->session = $session;

        $this->authorizeSession();
        $this->fillForm();

        $this->compareWithId = $session->previousForSameTemplate()?->id ?? '';
    }

    public function hydrate(): void
    {
        $this->authorizeSession();
    }

    public function edit(): void
    {
        $this->fillForm();
        $this->formOpen = true;
        $this->resetValidation();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'performedOn' => ['required', 'date'],
            'location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->session->update([
            'name' => trim($validated['name']),
            'performed_on' => $validated['performedOn'],
            'location' => $validated['location'] ?: null,
            'notes' => $validated['notes'] ?: null,
        ]);

        $this->formOpen = false;
    }

    public function cancel(): void
    {
        $this->formOpen = false;
        $this->fillForm();
        $this->resetValidation();
    }

    public function delete(): void
    {
        foreach ($this->session->items()->with('sets')->get() as $item) {
            $item->sets()->get()->each->delete();
            $item->delete();
        }

        $this->session->delete();

        $this->redirectRoute('history.index', navigate: true);
    }

    public function render(): View
    {
        $comparingWith = $this->comparingWith();

        return view('livewire.history.show', [
            'items' => $this->session->items()->with(['exercise', 'sets'])->get(),
            'comparingWith' => $comparingWith,
            'comparison' => $this->comparison($comparingWith),
            'candidates' => $this->candidates(),
        ]);
    }

    private function comparingWith(): ?WorkoutSession
    {
        if ($this->compareWithId === '') {
            return null;
        }

        return WorkoutSession::query()
            ->where('user_id', auth()->id())
            ->whereKeyNot($this->session->id)
            ->with('items.sets')
            ->find($this->compareWithId);
    }

    /**
     * One row per exercise of this workout, paired with the same exercise in
     * the workout being compared against.
     *
     * @return Collection<int, array{item: SessionItem, previous: SessionItem|null, currentSets: Collection<int, SessionSet>, previousSets: Collection<int, SessionSet>, delta: array{load: float, reps: int}|null}>
     */
    private function comparison(?WorkoutSession $other): Collection
    {
        if ($other === null) {
            return collect();
        }

        $previousItems = $other->items->keyBy('exercise_id');

        return $this->session->items()->with(['exercise', 'sets'])->get()
            ->map(function (SessionItem $item) use ($previousItems): array {
                $previous = $previousItems->get($item->exercise_id);

                $played = fn (Collection $sets) => $sets->where('is_warmup', false)->values();

                return [
                    'item' => $item,
                    'previous' => $previous,
                    'currentSets' => $played($item->sets),
                    'previousSets' => $previous === null ? collect() : $played($previous->sets),
                    'delta' => $item->deltaAgainst($previous),
                ];
            });
    }

    /**
     * @return Collection<int, WorkoutSession>
     */
    private function candidates(): Collection
    {
        return WorkoutSession::query()
            ->where('user_id', auth()->id())
            ->whereKeyNot($this->session->id)
            ->whereNotNull('finished_at')
            ->orderByDesc('performed_on')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();
    }

    private function fillForm(): void
    {
        $this->name = $this->session->name;
        $this->performedOn = $this->session->performed_on->toDateString();
        $this->location = $this->session->location ?? '';
        $this->notes = $this->session->notes ?? '';
    }

    private function authorizeSession(): void
    {
        abort_unless($this->session->user_id === auth()->id(), 404);
    }
}

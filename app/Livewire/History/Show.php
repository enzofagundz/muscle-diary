<?php

namespace App\Livewire\History;

use App\Models\WorkoutSession;
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

    public function mount(WorkoutSession $session): void
    {
        $this->session = $session;

        $this->authorizeSession();
        $this->fillForm();
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
            $item->sets()->delete();
            $item->delete();
        }

        $this->session->delete();

        $this->redirectRoute('history.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.history.show', [
            'items' => $this->session->items()->with(['exercise', 'sets'])->get(),
        ]);
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

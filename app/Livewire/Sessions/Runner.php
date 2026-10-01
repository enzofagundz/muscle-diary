<?php

namespace App\Livewire\Sessions;

use App\Models\WorkoutSession;
use Illuminate\View\View;
use Livewire\Component;

class Runner extends Component
{
    public WorkoutSession $session;

    public function mount(WorkoutSession $session): void
    {
        $this->session = $session;

        $this->authorizeSession();
    }

    public function hydrate(): void
    {
        $this->authorizeSession();
    }

    public function render(): View
    {
        return view('livewire.sessions.runner', [
            'items' => $this->session->items()->with('exercise')->get(),
        ]);
    }

    private function authorizeSession(): void
    {
        abort_unless($this->session->user_id === auth()->id(), 404);
    }
}

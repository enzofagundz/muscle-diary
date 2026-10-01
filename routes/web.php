<?php

use App\Livewire\Dashboard;
use App\Livewire\Exercises;
use App\Livewire\History;
use App\Livewire\Login;
use App\Livewire\Sessions;
use App\Livewire\Templates;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
});

Route::middleware('auth')->group(function () {
    Route::get('/', Dashboard::class)->name('dashboard');

    Route::get('/exercises', Exercises::class)->name('exercises.index');

    Route::get('/templates', Templates\Index::class)->name('templates.index');
    Route::get('/templates/{template}', Templates\Show::class)->name('templates.edit');

    Route::get('/sessions/{session}', Sessions\Runner::class)->name('sessions.run');

    Route::get('/history', History\Index::class)->name('history.index');
    Route::get('/history/{session}', History\Show::class)->name('history.show');

    Route::post('/logout', function (Request $request) {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');
});

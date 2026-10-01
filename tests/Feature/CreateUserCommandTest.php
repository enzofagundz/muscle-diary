<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('creates a user from the command line', function () {
    $this->artisan('app:user', [
        'email' => 'Enzo@Example.com',
        '--name' => 'Enzo',
        '--password' => 'secret-password',
    ])->assertSuccessful();

    $user = User::where('email', 'enzo@example.com')->sole();

    expect($user->name)->toBe('Enzo')
        ->and(Hash::check('secret-password', $user->password))->toBeTrue();
});

it('defaults the name to the local part of the e-mail', function () {
    $this->artisan('app:user', [
        'email' => 'enzo@example.com',
        '--password' => 'secret-password',
    ])->assertSuccessful();

    expect(User::where('email', 'enzo@example.com')->sole()->name)->toBe('enzo');
});

it('refuses an e-mail that already has an account', function () {
    User::factory()->create(['email' => 'enzo@example.com']);

    $this->artisan('app:user', [
        'email' => 'enzo@example.com',
        '--password' => 'secret-password',
    ])->assertFailed();

    expect(User::where('email', 'enzo@example.com')->count())->toBe(1);
});

it('refuses a password that is too short', function () {
    $this->artisan('app:user', [
        'email' => 'enzo@example.com',
        '--password' => 'curta',
    ])->assertFailed();

    expect(User::count())->toBe(0);
});

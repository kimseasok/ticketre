<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;

it('authenticates user with correct credentials', function () {
    $user = User::factory()->create(['password' => bcrypt('secret-pass')]);

    $credentials = ['email' => $user->email, 'password' => 'secret-pass'];

    expect(Auth::attempt($credentials))->toBeTrue();
});

it('rejects invalid credentials', function () {
    $user = User::factory()->create(['password' => bcrypt('secret-pass')]);

    $credentials = ['email' => $user->email, 'password' => 'wrong'];

    expect(Auth::attempt($credentials))->toBeFalse();
});

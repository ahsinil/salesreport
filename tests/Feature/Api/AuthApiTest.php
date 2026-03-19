<?php

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

test('approved users can log in through the api and receive a token', function () {
    $user = User::factory()->create([
        'email' => 'api-user@example.com',
    ]);
    $user->assignRole('sales');

    $this->postJson(route('api.auth.login'), [
        'email' => 'api-user@example.com',
        'password' => 'password',
        'device_name' => 'iphone',
    ])
        ->assertOk()
        ->assertJsonPath('message', 'Login berhasil.')
        ->assertJsonPath('user.email', 'api-user@example.com')
        ->assertJsonPath('roles.0', 'sales')
        ->assertJsonStructure([
            'token',
            'token_type',
            'user',
            'roles',
            'permissions',
        ]);

    expect($user->fresh()->tokens)->toHaveCount(1);
});

test('unapproved users can not log in through the api', function () {
    User::factory()->create([
        'email' => 'pending@example.com',
        'is_approved' => false,
    ]);

    $this->postJson(route('api.auth.login'), [
        'email' => 'pending@example.com',
        'password' => 'password',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

test('authenticated users can log out through the api', function () {
    $user = User::factory()->create();
    $token = $user->createToken('api-test')->plainTextToken;

    $this->withToken($token)
        ->postJson(route('api.auth.logout'))
        ->assertOk()
        ->assertJsonPath('message', 'Logout berhasil.');

    expect($user->fresh()->tokens)->toHaveCount(0);
});

<?php

use App\Enums\UserRole;
use App\Models\HotelProfile;
use App\Models\User;

it('creates the first admin with a random password and random PIN', function () {
    $this->artisan('sangatv:install', ['email' => 'it@hotel.example', '--hotel' => 'Napolin Hotel'])
        ->expectsOutputToContain('Kata sandi')
        ->assertSuccessful();

    $user = User::firstWhere('email', 'it@hotel.example');
    expect($user->role)->toBe(UserRole::Admin)
        ->and(HotelProfile::current()->name)->toBe('Napolin Hotel')
        ->and(HotelProfile::current()->technician_pin)->not->toBeNull();

    $this->artisan('sangatv:install', ['email' => 'it@hotel.example'])->assertFailed();
});

it('refuses to seed demo data in production', function () {
    $this->app['env'] = 'production';
    expect(fn () => app(Database\Seeders\DatabaseSeeder::class)->run())->toThrow(RuntimeException::class, 'production');
});

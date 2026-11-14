<?php

use App\Enums\UserRole;
use App\Filament\Resources\Slides\Pages\ManageSlides;
use App\Models\Device;
use App\Models\HotelProfile;
use App\Models\Slide;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('accepts a typical phone photo as slide image', function () {
    Storage::fake('public');
    $this->actingAs(User::factory()->create(['role' => UserRole::FrontOffice]));

    // reported as ~2.5 MB like a phone photo
    $photo = UploadedFile::fake()->image('pantai.jpg', 1600, 1200)->size(2500);

    Livewire::test(ManageSlides::class)
        ->callAction('create', ['title' => 'Promo', 'image_path' => $photo, 'is_active' => true, 'sort_order' => 0])
        ->assertHasNoActionErrors();

    expect(Slide::firstWhere('title', 'Promo')->image_path)->toStartWith('slides/');
});

it('shrinks slide images to 1920×1080 jpeg', function () {
    Storage::fake('public');
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));

    Livewire::test(ManageSlides::class)
        ->callAction('create', ['title' => 'Besar', 'image_path' => UploadedFile::fake()->image('b.png', 1600, 1200), 'is_active' => true, 'sort_order' => 0])
        ->assertHasNoActionErrors();

    $path = Slide::firstWhere('title', 'Besar')->image_path;
    [$w, $h] = getimagesizefromstring(Storage::disk('public')->get($path));
    expect($path)->toEndWith('.jpg')->and([$w, $h])->toBe([1920, 1080]);
});

it('saves home background music and exposes it to the TV', function () {
    Storage::fake('public');
    $this->actingAs(User::factory()->create(['role' => UserRole::FrontOffice]));

    Livewire::test(ManageSlides::class)
        ->callAction('homeMusic', [
            'home_music_path' => UploadedFile::fake()->create('lounge.mp3', 900, 'audio/mpeg'),
            'home_music_volume' => 40,
            'home_music_enabled' => true,
        ])
        ->assertHasNoActionErrors();

    $hotel = HotelProfile::current();
    expect($hotel->home_music_enabled)->toBeTrue()->and($hotel->home_music_path)->toStartWith('music/');

    $device = Device::factory()->create();
    $this->withToken($device->createToken('tv', ['tv'])->plainTextToken)
        ->getJson('/api/v1/bootstrap')
        ->assertJsonPath('home_music.volume', 40)
        ->assertJsonPath('home_music.url', fn ($url) => str_contains($url, 'music/'));
});

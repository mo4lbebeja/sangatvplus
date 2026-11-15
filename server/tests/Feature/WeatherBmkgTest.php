<?php

use App\Enums\UserRole;
use App\Filament\Pages\HotelProfilePage;
use App\Models\Device;
use App\Models\Guest;
use App\Models\HotelProfile;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

function bmkgFake(): void
{
    $slot = fn ($h, $t, $id, $en) => ['utc_datetime' => now()->utc()->subHours($h)->format('Y-m-d H:i:s'), 't' => $t, 'weather' => 3, 'weather_desc' => $id, 'weather_desc_en' => $en];
    Http::fake(['api.bmkg.go.id/*' => Http::response([
        'lokasi' => ['adm4' => '32.05.13.2001', 'desa' => 'Pameungpeuk', 'kecamatan' => 'Pameungpeuk', 'kotkab' => 'Garut'],
        'data' => [['cuaca' => [[$slot(5, 26, 'Cerah', 'Sunny'), $slot(2, 28, 'Berawan', 'Mostly Cloudy')], [$slot(-1, 30, 'Hujan', 'Rain')]]]],
    ])]);
}

it('shows the current BMKG forecast with attribution, in the guest language', function () {
    bmkgFake();
    HotelProfile::current()->update(['bmkg_adm4' => '32.05.13.2001']);
    $device = Device::factory()->create();
    $this->withToken($device->createToken('tv', ['tv'])->plainTextToken);

    $this->getJson('/api/v1/bootstrap')->assertOk()
        ->assertJsonPath('weather.temperature', 28) // latest slot that already started
        ->assertJsonPath('weather.description', 'Berawan · BMKG');

    cache()->flush();
    Guest::factory()->create(['room_id' => $device->room_id, 'language' => 'en']);
    app('auth')->forgetGuards();
    $this->getJson('/api/v1/bootstrap')->assertJsonPath('weather.description', 'Mostly Cloudy · BMKG');
});

it('hides the weather without a BMKG code (Open-Meteo stays off)', function () {
    config(['services.weather.enabled' => false]);
    $device = Device::factory()->create();
    $this->withToken($device->createToken('tv', ['tv'])->plainTextToken);
    $this->getJson('/api/v1/bootstrap')->assertJsonPath('weather', null);
});

it('confirms the BMKG location when the code is saved', function () {
    bmkgFake();
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    HotelProfile::current()->update(['check_in_time' => '14:00', 'check_out_time' => '12:00', 'screensaver_minutes' => 30]);
    Livewire::test(HotelProfilePage::class)->fillForm(['bmkg_adm4' => '32.05.13.2001'])->call('save')
        ->assertHasNoFormErrors()->assertNotified('Cuaca BMKG: Pameungpeuk, Pameungpeuk, Garut');
    Livewire::test(HotelProfilePage::class)->fillForm(['bmkg_adm4' => '3205'])->call('save')->assertHasFormErrors(['bmkg_adm4']);
});

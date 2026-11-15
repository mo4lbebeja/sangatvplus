<?php

use App\Enums\UserRole;
use App\Filament\Pages\HotelProfilePage;
use App\Models\Channel;
use App\Models\Device;
use App\Models\Guest;
use App\Models\HotelProfile;
use App\Models\Room;
use App\Models\User;
use Livewire\Livewire;

it('derives the service status from the period and manual release', function () {
    $h = HotelProfile::current();
    expect($h->serviceStatus())->toBe('active');

    $h->update(['service_expires_at' => now()->addDays(10)]);
    expect($h->fresh()->serviceStatus())->toBe('expiring');

    $h->update(['service_expires_at' => now()->subMinute()]);
    expect($h->fresh()->serviceStatus())->toBe('released');

    $h->update(['service_expires_at' => now()->addYear(), 'service_released_at' => now()]);
    expect($h->fresh()->serviceStatus())->toBe('released');
});

it('tells every TV the service status in bootstrap and heartbeat', function () {
    $device = Device::factory()->create();
    $this->withToken($device->createToken('tv', ['tv'])->plainTextToken);

    $this->getJson('/api/v1/bootstrap')->assertOk()->assertJsonPath('service.status', 'active');
    HotelProfile::current()->update(['service_released_at' => now()]);
    $this->postJson('/api/v1/heartbeat', [])->assertOk()->assertJsonPath('service.status', 'released');
});

it('lets the vendor set the period and release from the command line', function () {
    $this->artisan('sangatv:layanan', ['--sampai' => now()->addDays(5)->toDateString()])->expectsOutputToContain('expiring')->assertSuccessful();
    $this->artisan('sangatv:layanan', ['--lepas' => true])->expectsOutputToContain('released')->assertSuccessful();
    $this->artisan('sangatv:layanan', ['--aktifkan' => true, '--sampai' => 'tanpa-batas'])->expectsOutputToContain('active')->assertSuccessful();
    expect(HotelProfile::current()->service_expires_at)->toBeNull();
});

it('releases and restores all TVs from the hotel profile (typed confirmation)', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));

    Livewire::test(HotelProfilePage::class)->callAction('releaseTvs', data: ['confirm' => 'lepas'])->assertHasActionErrors(['confirm']);
    expect(HotelProfile::current()->service_released_at)->toBeNull();

    Livewire::test(HotelProfilePage::class)->callAction('releaseTvs', data: ['confirm' => 'LEPASKAN'])->assertHasNoActionErrors();
    expect(HotelProfile::current()->serviceStatus())->toBe('released');
    $this->get('/admin')->assertSee('Layanan SangaTV Plus dilepas');

    Livewire::test(HotelProfilePage::class)->callAction('restoreService');
    expect(HotelProfile::current()->serviceStatus())->toBe('active');
});

it('shows an expiry warning banner on CMS pages', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    HotelProfile::current()->update(['service_expires_at' => now()->addDays(7)]);
    $this->get('/admin/rooms')->assertOk()->assertSee('Layanan SangaTV Plus berakhir');
});

it('exports the hotel data as a ZIP of CSV files without secrets (admin only)', function () {
    $hotel = HotelProfile::current();
    $hotel->fill(['name' => 'Hotel Ekspor', 'wifi_ssid' => 'Tamu', 'wifi_password' => 'rahasia-wifi'])->save();
    $room = Room::factory()->create(['number' => '701']);
    Guest::factory()->create(['room_id' => $room->id, 'name' => 'Sari']);
    Channel::factory()->create(['number' => 101, 'name' => 'TVRI']);

    $this->actingAs(User::factory()->create(['role' => UserRole::FrontOffice]));
    $this->get('/admin/hotel-profile/ekspor-data.zip')->assertForbidden();

    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $res = $this->get('/admin/hotel-profile/ekspor-data.zip')->assertOk();
    $file = $res->baseResponse->getFile()->getPathname();
    $zip = new ZipArchive;
    expect($zip->open($file))->toBeTrue();
    $names = collect(range(0, $zip->numFiles - 1))->map(fn ($i) => $zip->getNameIndex($i))->all();
    expect($names)->toContain('kamar.csv', 'tamu.csv', 'channel.csv', 'statistik_tontonan_harian.csv', 'BACA-SAYA.txt')
        ->and($zip->getFromName('tamu.csv'))->toContain('Sari')->toContain('701')
        ->and($zip->getFromName('channel.csv'))->toContain('TVRI');
    $all = collect($names)->map(fn ($n) => $zip->getFromName($n))->implode("\n");
    expect($all)->not->toContain('rahasia-wifi');
    $zip->close();
});

it('records which TVs report Mode lepas', function () {
    $device = Device::factory()->create();
    $this->withToken($device->createToken('tv', ['tv'])->plainTextToken);
    $this->postJson('/api/v1/heartbeat', ['service_released' => true])->assertOk();
    expect($device->fresh()->service_released)->toBeTrue();
});

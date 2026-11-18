<?php

use App\Enums\CommandType;
use App\Enums\UserRole;
use App\Filament\Resources\AppReleases\Pages\ManageAppReleases;
use App\Filament\Resources\Devices\Pages\ManageDevices;
use App\Models\AppRelease;
use App\Models\Device;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

function release(string $pkg, int $code, bool $active = true): AppRelease
{
    return AppRelease::query()->create(['package_name' => $pkg, 'version_code' => $code, 'version_name' => "v{$code}", 'apk_path' => "releases/{$pkg}-{$code}.apk", 'sha256' => str_repeat('a', 64), 'is_active' => $active]);
}

it('serves updates only for the TV app package and never to held TVs', function () {
    $staging = release('com.sangatv.plus.staging', 10);
    $rel = release('com.sangatv.plus', 3);
    expect($staging->fresh()->is_active)->toBeTrue(); // activating the release build did not retire staging

    $device = Device::factory()->create();
    $this->withToken($device->createToken('tv', ['tv'])->plainTextToken);

    // Old TVs send no package: treated as staging.
    $this->getJson('/api/v1/app/update?version_code=1')->assertJsonPath('update.version_code', 10);
    $this->getJson('/api/v1/app/update?version_code=1&package=com.sangatv.plus')->assertJsonPath('update.version_code', 3);
    $this->getJson('/api/v1/app/update?version_code=1&package=com.example.other')->assertJsonPath('update', null);

    $device->update(['hold_updates' => true]);
    app('auth')->forgetGuards(); // the test guard caches the device between requests
    $this->getJson('/api/v1/app/update?version_code=1')->assertJsonPath('update', null)->assertJsonPath('held', true);
});

it('keeps one active release per package', function () {
    $a = release('com.sangatv.plus.staging', 10);
    $b = release('com.sangatv.plus.staging', 11);
    expect($a->fresh()->is_active)->toBeFalse()->and($b->fresh()->is_active)->toBeTrue();
    release('com.sangatv.plus', 10); // same version code, other package: allowed
    expect(AppRelease::query()->where('version_code', 10)->count())->toBe(2);
});

it('skips held TVs when pushing an update from the CMS', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $free = Device::factory()->create();
    $held = Device::factory()->create(['hold_updates' => true]);

    Livewire::test(ManageAppReleases::class)->callAction('pushUpdate');

    expect($free->commands()->where('type', CommandType::CheckUpdate)->count())->toBe(1)
        ->and($held->commands()->count())->toBe(0);
});

it('updates one TV from the CMS without waiting, once, and never a held or current TV', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::It]));
    release('com.sangatv.plus', 14);
    $old = Device::factory()->create(['app_package' => 'com.sangatv.plus', 'app_version_code' => 13]);
    $held = Device::factory()->create(['app_package' => 'com.sangatv.plus', 'app_version_code' => 13, 'hold_updates' => true]);
    $current = Device::factory()->create(['app_package' => 'com.sangatv.plus', 'app_version_code' => 14]);

    Livewire::test(ManageDevices::class)
        ->assertActionVisible(TestAction::make('updateNow')->table($old))
        ->assertActionDisabled(TestAction::make('updateNow')->table($held))
        ->assertActionHidden(TestAction::make('updateNow')->table($current))
        ->callAction(TestAction::make('updateNow')->table($old));

    expect($old->commands()->where('type', CommandType::CheckUpdate)->count())->toBe(1)
        ->and($old->fresh()->queueUpdate())->toBeFalse() // already on its way
        ->and($held->queueUpdate())->toBeFalse()
        ->and($current->queueUpdate())->toBeFalse();
});

it('updates all outdated TVs of the right package from the TV list', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    release('com.sangatv.plus.staging', 14);
    $legacy = Device::factory()->create(['app_package' => null, 'app_version_code' => 9]); // old TVs report no package
    $otherPackage = Device::factory()->create(['app_package' => 'com.sangatv.plus', 'app_version_code' => 9]);

    Livewire::test(ManageDevices::class)->callAction('updateAll');

    expect($legacy->commands()->count())->toBe(1)->and($otherPackage->commands()->count())->toBe(0);
});

it('stores the local IP the TV reports and ignores a malformed one', function () {
    $device = Device::factory()->create();
    $this->withToken($device->createToken('tv', ['tv'])->plainTextToken);

    $this->postJson('/api/v1/heartbeat', ['local_ip' => '10.10.80.28'])->assertOk();
    expect($device->fresh()->local_ip)->toBe('10.10.80.28');

    $this->postJson('/api/v1/heartbeat', ['local_ip' => 'bukan-ip'])->assertOk();
    expect($device->fresh()->local_ip)->toBe('10.10.80.28');
});

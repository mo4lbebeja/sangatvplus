<?php

use App\Models\AppRelease;
use App\Models\Central\Hotel;
use App\Models\Central\VendorUser;
use App\Models\Device;
use App\Models\HotelProfile;
use App\Models\PmsIntegration;
use App\Models\ProvisioningCode;
use App\Models\Room;
use App\Models\User;
use App\Tenancy\HotelManager;
use App\Vendor\Resources\Hotels\Pages\ManageHotels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Two real hotels (alpha, bravo), each with its own PostgreSQL database, plus a central database.
 * Proves that TVs, PMS and CMS staff only ever reach their own hotel's data.
 */
function tenancyOn(): HotelManager
{
    config([
        'tenancy.enabled' => true,
        'tenancy.database_prefix' => 'sangatv_test_',
        'tenancy.legacy_hotel' => 'alpha',
        'database.connections.central.database' => 'sangatv_test_central',
    ]);
    DB::purge('central');

    return app(HotelManager::class);
}

beforeEach(function () {
    $main = DB::connection('pgsql');
    foreach (['sangatv_test_central', 'sangatv_test_alpha', 'sangatv_test_bravo'] as $db) {
        $main->statement("DROP DATABASE IF EXISTS \"{$db}\" WITH (FORCE)");
    }
    $main->statement('CREATE DATABASE "sangatv_test_central"');

    $this->tenancy = tenancyOn();
    Artisan::call('migrate', ['--database' => 'central', '--path' => 'database/migrations/central', '--force' => true]);

    $this->alpha = $this->tenancy->provision('alpha', 'Hotel Alpha', 'admin@alpha.test')['hotel'];
    $this->bravo = $this->tenancy->provision('bravo', 'Hotel Bravo', 'admin@bravo.test')['hotel'];
    foreach ([$this->alpha, $this->bravo] as $h) {
        $this->tenancy->run($h, function () use ($h) {
            User::query()->each(fn (User $u) => $u->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save()); // via the encrypted cast
            Room::query()->create(['number' => $h->slug === 'alpha' ? 'A-101' : 'B-201', 'floor' => 1]);
        });
    }
});

afterEach(function () {
    $this->tenancy->end();
    DB::purge('central');
    $main = DB::connection('pgsql');
    foreach (['sangatv_test_central', 'sangatv_test_alpha', 'sangatv_test_bravo'] as $db) {
        $main->statement("DROP DATABASE IF EXISTS \"{$db}\" WITH (FORCE)");
    }
});

/** Issue a TV registration code inside a hotel, as its staff would in the CMS. */
function codeFor(Hotel $hotel): string
{
    return app(HotelManager::class)->run($hotel, fn () => ProvisioningCode::issue(Room::query()->first())[1]);
}

function provisionTv(string $code): array
{
    return test()->postJson('/api/v1/devices/provision', [
        'code' => $code, 'device_uid' => 'uid-'.uniqid(), 'model' => 'T', 'android_version' => '14', 'app_version' => '1', 'app_version_code' => 1, 'device_owner' => false,
    ])->assertCreated()->json();
}

it('creates each hotel with its own database, profile and admin', function () {
    expect(Hotel::query()->pluck('database')->all())->toBe(['sangatv_test_alpha', 'sangatv_test_bravo']);
    expect($this->tenancy->run($this->alpha, fn () => [HotelProfile::current()->name, User::query()->value('email')]))->toBe(['Hotel Alpha', 'admin@alpha.test'])
        ->and($this->tenancy->run($this->bravo, fn () => [HotelProfile::current()->name, User::query()->value('email')]))->toBe(['Hotel Bravo', 'admin@bravo.test']);
});

it('registers a TV to the hotel that issued the code and keeps TV data apart', function () {
    $b = provisionTv(codeFor($this->bravo));
    expect($b['hotel'])->toBe('bravo')->and($b['device']['room'])->toBe('B-201');
    expect($this->tenancy->run($this->bravo, fn () => Device::query()->count()))->toBe(1)
        ->and($this->tenancy->run($this->alpha, fn () => Device::query()->count()))->toBe(0);

    // Bravo's TV, with its hotel header, sees Bravo.
    $this->withToken($b['token'])->withHeader('X-SangaTV-Hotel', 'bravo')->getJson('/api/v1/bootstrap')
        ->assertOk()->assertJsonPath('hotel.name', 'Hotel Bravo')->assertJsonPath('room.number', 'B-201');

    // The same token presented to Alpha is unknown there.
    app('auth')->forgetGuards();
    $this->withToken($b['token'])->withHeader('X-SangaTV-Hotel', 'alpha')->getJson('/api/v1/bootstrap')->assertUnauthorized();
});

it('sends TVs without a hotel header to the legacy (first) hotel', function () {
    $a = provisionTv(codeFor($this->alpha));
    expect($a['hotel'])->toBe('alpha');
    app('auth')->forgetGuards();
    $this->withToken($a['token'])->getJson('/api/v1/bootstrap')->assertOk()->assertJsonPath('hotel.name', 'Hotel Alpha');
    $this->withToken($a['token'])->withHeader('X-SangaTV-Hotel', 'tidak-ada')->getJson('/api/v1/bootstrap')->assertUnauthorized();
});

it('routes PMS calls by API key to the right hotel', function () {
    $key = $this->tenancy->run($this->bravo, function () {
        $i = PmsIntegration::query()->create(['name' => 'PMS Bravo', 'key_id' => PmsIntegration::newKeyId(), 'secret' => 's3cret', 'auth_mode' => 'bearer', 'is_active' => true]);

        return $i->key_id;
    });
    $this->withHeaders(['X-SangaTV-Key' => $key, 'Authorization' => 'Bearer s3cret'])->getJson('/api/pms/v1/ping')
        ->assertOk()->assertJsonPath('data.hotel', 'Hotel Bravo');
});

it('asks the CMS for a hotel code first and only shows that hotel to its staff', function () {
    $this->get('/admin/rooms')->assertRedirect('/admin/login');
    $this->get('/admin/login')->assertOk()->assertSee('Kode Hotel');

    $admin = $this->tenancy->run($this->bravo, fn () => User::query()->first());
    $this->withSession(['hotel_id' => $this->bravo->id])->actingAs($admin)
        ->get('/admin/rooms')->assertOk()->assertSee('B-201')->assertDontSee('A-101');
});

it('locks a suspended hotel out of CMS and API', function () {
    $a = provisionTv(codeFor($this->alpha));
    $this->alpha->update(['status' => 'suspended']);
    app('auth')->forgetGuards();
    $this->withToken($a['token'])->withHeader('X-SangaTV-Hotel', 'alpha')->getJson('/api/v1/bootstrap')->assertForbidden();
    $this->withSession(['hotel_id' => $this->alpha->id])->get('/admin/rooms')->assertRedirect('/admin/login');
});

it('keeps public files and the rental period per hotel', function () {
    expect($this->tenancy->run($this->bravo, fn () => Storage::disk('public')->path('logo.png')))->toContain('hotels/bravo/logo.png')
        ->and($this->tenancy->run($this->alpha, fn () => Storage::disk('public')->path('logo.png')))->toContain('hotels/alpha/logo.png');

    $this->bravo->update(['service_released_at' => now()]);
    expect($this->tenancy->run($this->bravo->fresh(), fn () => HotelProfile::current()->serviceStatus()))->toBe('released')
        ->and($this->tenancy->run($this->alpha, fn () => HotelProfile::current()->serviceStatus()))->toBe('active');
});

it('runs scheduled jobs once per active hotel', function () {
    $this->bravo->update(['status' => 'suspended']);
    $this->artisan('sangatv:semua-hotel', ['perintah' => 'sangatv:privasi-tamu'])
        ->expectsOutputToContain('== alpha')->doesntExpectOutputToContain('== bravo')->assertSuccessful();
});

it('tells older TVs their hotel code in the bootstrap', function () {
    $a = provisionTv(codeFor($this->alpha));
    app('auth')->forgetGuards();
    $this->withToken($a['token'])->getJson('/api/v1/bootstrap')->assertJsonPath('hotel_code', 'alpha');
});

// ------------------------------------------------------------------ vendor panel (/sijalucom)

function vendorLogin(): VendorUser
{
    $v = VendorUser::query()->create(['name' => 'Sijalucom', 'email' => 'ops@sijalucom.test', 'password' => 'rahasia-panjang-1']);
    $v->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();
    test()->actingAs($v, 'vendor');
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('vendor'));

    return $v;
}

it('keeps hotel staff out of the vendor panel and vendor staff out of hotel data', function () {
    $this->get('/sijalucom')->assertRedirect('/sijalucom/login');
    $staff = $this->tenancy->run($this->alpha, fn () => User::query()->first());
    $this->actingAs($staff)->get('/sijalucom')->assertRedirect('/sijalucom/login');
    app('auth')->forgetGuards();
    vendorLogin();
    $this->get('/sijalucom')->assertOk()->assertSee('Hotel aktif');
    $this->get('/sijalucom/hotels')->assertOk()->assertSee('alpha')->assertSee('bravo');
});

it('lets Sijalucom create a hotel with its own database, admin and the current app release', function () {
    $this->tenancy->run($this->alpha, fn () => AppRelease::query()->create([
        'package_name' => 'com.sangatv.plus', 'version_code' => 12, 'version_name' => '0.4.0', 'apk_path' => 'releases/x.apk', 'sha256' => str_repeat('a', 64), 'is_active' => true,
    ]));
    vendorLogin();
    Livewire\Livewire::test(ManageHotels::class)
        ->callAction('create', ['slug' => 'charlie', 'name' => 'Hotel Charlie', 'admin_email' => 'gm@charlie.test', 'service_expires_at' => '2027-12-31'])
        ->assertHasNoActionErrors()->assertNotified('Hotel Hotel Charlie siap');

    $c = Hotel::query()->where('slug', 'charlie')->firstOrFail();
    expect($c->database)->toBe('sangatv_test_charlie')->and($c->service_expires_at->toDateString())->toBe('2027-12-31');
    expect($this->tenancy->run($c, fn () => [User::query()->value('email'), AppRelease::query()->value('version_code')]))->toBe(['gm@charlie.test', 12]);
    $this->tenancy->dropDatabase($c);
});

it('rejects a duplicate or reserved hotel code', function () {
    vendorLogin();
    Livewire\Livewire::test(ManageHotels::class)
        ->callAction('create', ['slug' => 'alpha', 'name' => 'X', 'admin_email' => 'x@x.test'])->assertHasActionErrors(['slug']);
    Livewire\Livewire::test(ManageHotels::class)
        ->callAction('create', ['slug' => 'sijalucom', 'name' => 'X', 'admin_email' => 'x@x.test'])->assertHasActionErrors(['slug']);
});

it('suspends a hotel and releases its TVs from the vendor panel', function () {
    $tv = provisionTv(codeFor($this->bravo));
    vendorLogin();
    Livewire\Livewire::test(ManageHotels::class)
        ->callTableAction('release', $this->bravo)
        ->callTableAction('suspend', $this->alpha);

    expect($this->bravo->fresh()->serviceStatus())->toBe('released')->and($this->alpha->fresh()->status)->toBe('suspended');
    app('auth')->forgetGuards();
    $this->withToken($tv['token'])->withHeader('X-SangaTV-Hotel', 'bravo')->getJson('/api/v1/bootstrap')->assertOk()->assertJsonPath('service.status', 'released');
});

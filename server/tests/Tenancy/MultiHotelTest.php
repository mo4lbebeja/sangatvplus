<?php

use App\Mail\InvoiceMail;
use App\Models\AppRelease;
use App\Models\AuditLog;
use App\Models\Central\Hotel;
use App\Models\Central\Invoice;
use App\Models\Central\Plan;
use App\Models\Central\VendorActivity;
use App\Models\Central\VendorSetting;
use App\Models\Central\VendorUser;
use App\Models\Device;
use App\Models\HotelProfile;
use App\Models\PmsIntegration;
use App\Models\ProvisioningCode;
use App\Models\Room;
use App\Models\User;
use App\Services\Notifier;
use App\Tenancy\HotelManager;
use App\Vendor\Billing;
use App\Vendor\Impersonation;
use App\Vendor\Resources\Hotels\Pages\ManageHotels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
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
    foreach (['sangatv_test_central', 'sangatv_test_alpha', 'sangatv_test_bravo', 'sangatv_test_charlie'] as $db) {
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
    foreach (['alpha', 'bravo', 'charlie'] as $slug) {
        File::deleteDirectory(storage_path("app/public/hotels/{$slug}")); // else deploy.sh copies the empty folders
    }
    DB::purge('central');
    $main = DB::connection('pgsql');
    foreach (['sangatv_test_central', 'sangatv_test_alpha', 'sangatv_test_bravo', 'sangatv_test_charlie'] as $db) {
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

/** Rp 500.000 for the first 10 TVs, Rp 50.000 per extra TV, per month (20 TVs = Rp 1.000.000 + 500.000). */
function testPlan(): Plan
{
    return Plan::query()->firstOrCreate(['code' => 'pro'], ['name' => 'Pro', 'base_price' => 1_000_000, 'included_tvs' => 10, 'price_per_tv' => 50_000]);
}

function vendorLogin(string $role = 'owner'): VendorUser
{
    $v = VendorUser::query()->create(['name' => 'Sijalucom', 'email' => "{$role}@sijalucom.test", 'role' => $role, 'password' => 'rahasia-panjang-1']);
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
    $this->get('/sijalucom')->assertOk()->assertSee('Pendapatan berulang');
    $this->get('/sijalucom/hotel')->assertOk()->assertSee('alpha')->assertSee('bravo');
});

it('lets Sijalucom create a hotel with its own database, admin and the current app release', function () {
    $this->tenancy->run($this->alpha, fn () => AppRelease::query()->create([
        'package_name' => 'com.sangatv.plus', 'version_code' => 12, 'version_name' => '0.4.0', 'apk_path' => 'releases/x.apk', 'sha256' => str_repeat('a', 64), 'is_active' => true,
    ]));
    vendorLogin();
    Livewire\Livewire::test(ManageHotels::class)
        ->callAction('create', ['slug' => 'charlie', 'name' => 'Hotel Charlie', 'admin_email' => 'gm@charlie.test', 'plan_id' => testPlan()->id,
            'tv_quota' => 20, 'billing_months' => 3, 'discount_percent' => 0, 'auto_invoice' => true, 'activation' => 'paid', 'months' => 3, 'method' => 'transfer'])
        ->assertHasNoActionErrors()->assertNotified();

    $c = Hotel::query()->where('slug', 'charlie')->firstOrFail();
    $end = now('Asia/Jakarta')->startOfDay()->addMonthsNoOverflow(3)->subDay()->toDateString();
    expect($c->database)->toBe('sangatv_test_charlie')->and($c->service_expires_at->setTimezone('Asia/Jakarta')->toDateString())->toBe($end)
        ->and($c->invoices()->sole()->only(['status', 'total', 'months']))->toBe(['status' => 'paid', 'total' => 3 * 1_500_000, 'months' => 3]);
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

it('lets Sijalucom open a hotel CMS as its admin, logged in that hotel, and return', function () {
    $v = vendorLogin();
    $adminId = $this->tenancy->run($this->bravo, fn () => User::query()->value('id'));
    Livewire\Livewire::test(ManageHotels::class)
        ->callTableAction('impersonate', $this->bravo, ['user_id' => $adminId])->assertHasNoTableActionErrors()->assertRedirect(url('/admin'));

    expect(session('hotel_id'))->toBe($this->bravo->id);
    $this->tenancy->end();
    $this->get('/admin')->assertOk()->assertSee('Mode dukungan Sijalucom')->assertSee('Hotel Bravo');
    expect($this->tenancy->run($this->bravo, fn () => AuditLog::query()->where('event', 'vendor.masuk_sebagai')->value('properties')))->toBe(['vendor' => $v->email]);

    $this->tenancy->end();
    $this->get('/admin/selesai-sijalucom')->assertRedirect('/sijalucom/hotel');
    expect(session('hotel_id'))->toBeNull()->and(session(Impersonation::SESSION))->toBeNull();
});

it('reminds Sijalucom about rentals ending soon, labelled per hotel', function () {
    config(['mail.default' => 'array', 'sangatv.telegram.bot_token' => 'T', 'sangatv.telegram.chat_id' => '1']);
    Http::fake(['api.telegram.org/*' => fn () => Http::response(['ok' => true])]);
    $this->alpha->update(['service_expires_at' => now('Asia/Jakarta')->addDays(14)->setTime(23, 0)->utc()]);
    $this->bravo->update(['service_expires_at' => now('Asia/Jakarta')->addDays(20)]);
    VendorUser::query()->create(['name' => 'S', 'email' => 'ops@sijalucom.test', 'password' => 'x-very-long-pass']);

    $this->artisan('sangatv:pengingat-sewa')->expectsOutputToContain('Hotel Alpha (alpha): berakhir 14 hari lagi')->assertSuccessful();
    Http::assertSent(fn ($r) => str_contains($r['text'], '[Sijalucom] 🗓️ Masa sewa hotel') && ! str_contains($r['text'], 'bravo'));

    expect($this->tenancy->run($this->bravo, fn () => app(Notifier::class)->label()))->toBe('Hotel Bravo · bravo')
        ->and(app(Notifier::class)->label())->toBe('Sijalucom · pusat')
        ->and(app(Notifier::class)->vendorRecipients())->toContain('ops@sijalucom.test');
});

it('counts rental periods in whole months from the activation day', function () {
    $this->travelTo(Carbon::parse('2026-11-16 10:00', 'Asia/Jakarta'));
    $this->alpha->update(['plan_id' => testPlan()->id, 'tv_quota' => 20]);
    [$start, $end] = Billing::nextPeriod($this->alpha->fresh(), 1);
    expect([$start->toDateString(), $end->toDateString()])->toBe(['2026-11-16', '2026-12-15']);

    $this->travelTo(Carbon::parse('2027-01-31 10:00', 'Asia/Jakarta'));
    [$s2, $e2] = Billing::nextPeriod($this->bravo, 1);
    expect([$s2->toDateString(), $e2->toDateString()])->toBe(['2027-01-31', '2027-02-27']); // no overflow into March
});

it('invoices a rental, extends it only when paid, then continues from the old end date', function () {
    $this->travelTo(Carbon::parse('2026-11-16 10:00', 'Asia/Jakarta'));
    VendorSetting::put(['tax_percent' => 11, 'due_days' => 7]);
    $this->alpha->update(['plan_id' => testPlan()->id, 'tv_quota' => 20, 'discount_percent' => 10]);
    $hotel = $this->alpha->fresh();
    expect($hotel->monthlyPrice())->toBe(1_350_000);

    $inv = Billing::createInvoice($hotel, 3);
    expect($inv->number)->toBe('INV/202611/0001')
        ->and([$inv->subtotal, $inv->discount, $inv->tax, $inv->total])->toBe([4_500_000, 450_000, 445_500, 4_495_500])
        ->and($inv->due_at->toDateString())->toBe('2026-11-23')
        ->and($hotel->fresh()->service_expires_at)->toBeNull(); // not applied before payment

    Billing::markPaid($inv, '2026-11-18', 'transfer', 'TRX-1');
    expect($hotel->fresh()->service_expires_at->setTimezone('Asia/Jakarta')->format('Y-m-d H:i'))->toBe('2027-02-15 23:59')
        ->and($hotel->fresh()->activated_at)->not->toBeNull();

    $next = Billing::createInvoice($hotel->fresh(), 1, applyNow: true);
    expect($next->number)->toBe('INV/202611/0002')->and($next->period_start->toDateString())->toBe('2027-02-16')
        ->and($hotel->fresh()->service_expires_at->setTimezone('Asia/Jakarta')->toDateString())->toBe('2027-03-15');
    expect(fn () => Billing::markPaid($inv->fresh(), '2026-11-18', 'cash'))->toThrow(RuntimeException::class);
    expect(VendorActivity::query()->where('hotel_id', $hotel->id)->pluck('action')->all())
        ->toContain('invoice.created', 'invoice.paid', 'rental.extended');
});

it('creates renewal invoices automatically before a rental ends and reports overdue ones', function () {
    config(['mail.default' => 'array', 'sangatv.telegram.bot_token' => 'T', 'sangatv.telegram.chat_id' => '1']);
    Http::fake(['api.telegram.org/*' => fn () => Http::response(['ok' => true])]);
    Mail::fake();
    $this->alpha->update(['plan_id' => testPlan()->id, 'tv_quota' => 10, 'billing_email' => 'finance@alpha.test', 'service_expires_at' => now('Asia/Jakarta')->addDays(10)->toDateString()]);
    $this->bravo->update(['plan_id' => testPlan()->id, 'service_expires_at' => now('Asia/Jakarta')->addDays(40)->toDateString()]);

    $this->artisan('sangatv:tagihan')->assertSuccessful();
    $this->artisan('sangatv:tagihan')->assertSuccessful(); // idempotent

    expect(Invoice::query()->pluck('hotel_id')->all())->toBe([$this->alpha->id]);
    Mail::assertSent(InvoiceMail::class, fn ($m) => $m->hasTo('finance@alpha.test'));
    Http::assertSent(fn ($r) => str_contains($r['text'], 'Tagihan baru') && str_contains($r['text'], 'Hotel Alpha'));
});

it('refuses to register more TVs than the contracted quota', function () {
    $this->bravo->update(['tv_quota' => 1]);
    provisionTv(codeFor($this->bravo));
    app('auth')->forgetGuards();
    $this->postJson('/api/v1/devices/provision', ['code' => codeFor($this->bravo), 'device_uid' => 'uid-extra', 'app_version' => '1', 'app_version_code' => 1])
        ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'Kuota TV'));
});

it('renders every console page and the printable invoice for the owner', function () {
    $this->alpha->update(['plan_id' => testPlan()->id, 'tv_quota' => 12]);
    $inv = Billing::createInvoice($this->alpha->fresh(), 1);
    vendorLogin();
    foreach (['/sijalucom', '/sijalucom/hotel', "/sijalucom/hotel/{$this->alpha->id}", '/sijalucom/tagihan', "/sijalucom/tagihan/{$inv->id}",
        '/sijalucom/paket', '/sijalucom/laporan', '/sijalucom/pengaturan', '/sijalucom/tim', '/sijalucom/aktivitas'] as $url) {
        $this->get($url)->assertOk();
    }
    $this->get("/sijalucom/tagihan/{$inv->id}/cetak")->assertOk()->assertSee($inv->number)->assertSee('Rp 1.100.000');
    $this->get('/sijalucom/laporan/tagihan.csv')->assertOk()->assertDownload();
});

it('limits console areas by role', function () {
    vendorLogin('support');
    $this->get('/sijalucom/hotel')->assertOk();
    $this->get('/sijalucom/tagihan')->assertForbidden();
    $this->get('/sijalucom/pengaturan')->assertForbidden();
    $this->get('/sijalucom/laporan/tagihan.csv')->assertForbidden();
});

<?php

use App\Enums\CommandStatus;
use App\Enums\CommandType;
use App\Enums\GuestStatus;
use App\Enums\SourceType;
use App\Enums\UserRole;
use App\Filament\Pages\HotelProfilePage;
use App\Filament\Resources\ChannelProviders\Pages\ManageChannelProviders;
use App\Filament\Resources\Channels\Pages\ManageChannels;
use App\Filament\Resources\Guests\Pages\ManageGuests;
use App\Filament\Resources\Rooms\Pages\ManageRooms;
use App\Models\AppWhitelist;
use App\Models\Channel;
use App\Models\ChannelProvider;
use App\Models\Device;
use App\Models\Guest;
use App\Models\HotelProfile;
use App\Models\ProvisioningCode;
use App\Models\Room;
use App\Models\User;
use App\Support\Audit;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

const ADMIN_PAGES = [
    '/admin/panduan-teknisi', '/admin/galeri', '/admin/integrasi-pms', '/admin/pms-kunci-api', '/admin/pms-log',
    '/admin', '/admin/guests', '/admin/rooms', '/admin/slides', '/admin/facilities', '/admin/hotel-profile',
    '/admin/channels', '/admin/channel-categories', '/admin/channel-providers', '/admin/announcements', '/admin/statistik-channel',
    '/admin/devices', '/admin/app-whitelist', '/admin/app-releases', '/admin/users', '/admin/audit-logs',
];

function actingAsRole(UserRole $role): User
{
    $u = User::factory()->create(['role' => $role]);
    test()->actingAs($u);

    return $u;
}

it('renders every page for an admin', function () {
    $this->seed();
    // Data that exercises relations on every list (strict mode catches N+1 / lazy loads).
    $device = Device::factory()->create(['room_id' => Room::first()->id, 'last_seen_at' => now()]);
    Audit::log('technician.pin_failed', $device, [], $device);
    Guest::factory()->create(['room_id' => Room::first()->id]);
    $device->commands()->create(['type' => CommandType::Refresh, 'status' => CommandStatus::Pending]);
    actingAsRole(UserRole::Admin);

    foreach (ADMIN_PAGES as $url) {
        $this->get($url)->assertOk();
    }
});

it('limits pages by role', function (UserRole $role, array $allowed) {
    actingAsRole($role);

    foreach (ADMIN_PAGES as $url) {
        $expected = in_array($url, $allowed, true) ? 200 : 403;
        expect($this->get($url)->status())->toBe($expected, "{$role->value} {$url}");
    }
})->with([
    'front office' => [UserRole::FrontOffice, ['/admin', '/admin/guests', '/admin/rooms', '/admin/slides', '/admin/facilities', '/admin/galeri', '/admin/announcements']],
    'it' => [UserRole::It, ['/admin', '/admin/panduan-teknisi', '/admin/integrasi-pms', '/admin/pms-kunci-api', '/admin/pms-log', '/admin/rooms', '/admin/channels', '/admin/channel-categories', '/admin/channel-providers', '/admin/announcements', '/admin/statistik-channel', '/admin/devices', '/admin/app-whitelist', '/admin/app-releases', '/admin/audit-logs']],
]);

it('blocks inactive users from the panel', function () {
    $this->actingAs(User::factory()->create(['is_active' => false]))->get('/admin')->assertForbidden();
});

it('lets front office check a guest in and out, queueing app-data wipe', function () {
    actingAsRole(UserRole::FrontOffice);
    $room = Room::factory()->create();
    Device::factory()->create(['room_id' => $room->id]);
    AppWhitelist::create(['package_name' => 'com.netflix.ninja', 'label' => 'Netflix']);

    Livewire::test(ManageGuests::class)
        ->callAction('create', ['room_id' => $room->id, 'salutation' => 'Bapak', 'name' => 'Budi', 'language' => 'id'])
        ->assertHasNoActionErrors();

    $guest = Guest::firstWhere('name', 'Budi');
    expect($guest->status)->toBe(GuestStatus::InHouse);

    Livewire::test(ManageGuests::class)->callAction(TestAction::make('checkout')->table($guest));

    expect($guest->fresh()->status)->toBe(GuestStatus::CheckedOut)
        ->and($room->devices()->first()->commands()->first()->type)->toBe(CommandType::ClearAppData);
});

it('does not let front office issue TV provisioning codes', function () {
    actingAsRole(UserRole::FrontOffice);
    $room = Room::factory()->create();

    Livewire::test(ManageRooms::class)->assertActionHidden(TestAction::make('provisioningCode')->table($room));
});

it('lets IT issue a provisioning code without storing the plain code', function () {
    actingAsRole(UserRole::It);
    $room = Room::factory()->create();

    Livewire::test(ManageRooms::class)->callAction(TestAction::make('provisioningCode')->table($room))->assertNotified();

    $code = ProvisioningCode::firstWhere('room_id', $room->id);
    expect($code->code_hash)->toHaveLength(64)->and($code->isUsable())->toBeTrue();
});

it('stores provider secrets encrypted and keeps them when left empty on edit', function () {
    actingAsRole(UserRole::It);

    Livewire::test(ManageChannelProviders::class)
        ->callAction('create', ['name' => 'Prov', 'signing' => 'hmac_query', 'token_ttl_seconds' => 600, 'secret' => 's3cret', 'is_active' => true])
        ->assertHasNoActionErrors();

    $p = ChannelProvider::firstWhere('name', 'Prov');
    expect($p->credentials['secret'])->toBe('s3cret')
        ->and(DB::table('channel_providers')->value('credentials'))->not->toContain('s3cret');

    Livewire::test(ManageChannelProviders::class)
        ->callAction(TestAction::make('edit')->table($p), ['name' => 'Prov 2', 'secret' => ''])
        ->assertHasNoActionErrors();

    expect($p->fresh())->name->toBe('Prov 2')->and($p->fresh()->credentials['secret'])->toBe('s3cret');
});

it('validates channel URL scheme by source type', function () {
    actingAsRole(UserRole::It);

    Livewire::test(ManageChannels::class)
        ->callAction('create', ['number' => 150, 'name' => 'X', 'source_type' => 'local_hls', 'url' => 'udp://239.1.1.1:1234'])
        ->assertHasActionErrors(['url']);

    Livewire::test(ManageChannels::class)
        ->callAction('create', ['number' => 150, 'name' => 'X', 'source_type' => 'local_multicast', 'url' => 'udp://239.1.1.1:1234', 'is_active' => true])
        ->assertHasNoActionErrors();
});

it('saves the hotel profile and hashes a new technician PIN', function () {
    actingAsRole(UserRole::Admin);

    Livewire::test(HotelProfilePage::class)
        ->fillForm(['name' => 'Hotel Baru', 'new_pin' => '123456'])
        ->call('save')
        ->assertHasFormErrors(['new_pin']); // too common

    Livewire::test(HotelProfilePage::class)
        ->fillForm(['name' => 'Hotel Baru', 'new_pin' => '582047', 'check_in_time' => '14:00', 'check_out_time' => '12:00', 'timezone' => 'Asia/Jakarta', 'screensaver_minutes' => 30])
        ->call('save')
        ->assertHasNoFormErrors();

    $hotel = HotelProfile::current();
    expect($hotel->name)->toBe('Hotel Baru')
        ->and($hotel->verifyTechnicianPin('582047'))->toBeTrue()
        ->and(json_encode($hotel->technician_pin))->not->toContain('582047');
});

it('imports channels from an M3U file, skipping duplicates and keeping them inactive', function () {
    actingAsRole(UserRole::It);
    Channel::factory()->create(['number' => 101, 'url' => 'https://cdn.example/dup.m3u8']);
    Http::fake(['logo.example/*' => Http::response(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='), 200)]);
    Storage::fake('public');

    $file = UploadedFile::fake()->createWithContent('list.m3u', <<<'M3U'
    #EXTM3U
    #EXTINF:-1 tvg-chno="101" tvg-logo="https://logo.example/a.png" group-title="Berita",Kanal A
    https://cdn.example/a.m3u8
    #EXTINF:-1,Kanal Duplikat
    https://cdn.example/dup.m3u8
    #EXTINF:-1,Kanal DASH
    https://cdn.example/b.mpd
    M3U);

    Livewire::test(ManageChannels::class)
        ->callAction('importM3u', ['mode' => 'file', 'file' => $file, 'origin' => 'provider', 'download_logos' => true, 'activate' => false])
        ->assertHasNoActionErrors()
        ->assertNotified();

    $a = Channel::firstWhere('name', 'Kanal A');
    expect($a->number)->toBe(102) // 101 taken → next free
        ->and($a->is_active)->toBeFalse()
        ->and($a->source_type)->toBe(SourceType::ProviderHls)
        ->and($a->category->name)->toBe('Berita')
        ->and($a->logo_path)->toStartWith('channel-logos/')
        ->and(Channel::firstWhere('name', 'Kanal DASH')->source_type)->toBe(SourceType::ProviderDash)
        ->and(Channel::where('url', 'https://cdn.example/dup.m3u8')->count())->toBe(1);

    $this->assertDatabaseHas('audit_logs', ['event' => 'channel.imported']);
});

it('prefills the technician guide with a valid TV ip only', function () {
    actingAsRole(UserRole::It);
    $this->get('/admin/panduan-teknisi?ip=192.168.101.4')->assertOk()->assertSee('192.168.101.4')->assertSee('add-role-holder android.app.role.HOME');
    $this->get('/admin/panduan-teknisi?ip='.urlencode('"><b>xss9</b>'))->assertOk()->assertDontSee('xss9', false);
});

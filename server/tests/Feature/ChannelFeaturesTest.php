<?php

use App\Enums\UserRole;
use App\Filament\Pages\ChannelStatsPage;
use App\Filament\Pages\HotelProfilePage;
use App\Filament\Resources\Announcements\Pages\ManageAnnouncements;
use App\Filament\Resources\Channels\Pages\ManageChannels;
use App\Filament\Resources\Slides\Pages\ManageSlides;
use App\Mail\ChannelUptimeReport;
use App\Models\Announcement;
use App\Models\Channel;
use App\Models\ChannelCheck;
use App\Models\ChannelView;
use App\Models\Device;
use App\Models\EpgProgramme;
use App\Models\Guest;
use App\Models\HotelProfile;
use App\Models\Room;
use App\Models\Slide;
use App\Models\User;
use App\Services\ChannelStats;
use App\Services\EpgImporter;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

function tvFor(Room $room): Device
{
    $device = Device::factory()->create(['room_id' => $room->id]);
    app('auth')->forgetGuards(); // the guard caches the previous device between requests
    test()->withToken($device->createToken('tv', ['tv'])->plainTextToken);

    return $device;
}

function xmltv(): string
{
    $now = now()->utc();
    $fmt = fn ($t) => $t->format('YmdHis').' +0000';
    $p = fn ($ch, $from, $to, $title, $extra = '') => "<programme start=\"{$fmt($from)}\" stop=\"{$fmt($to)}\" channel=\"{$ch}\">{$extra}<title lang=\"en\">{$title} EN</title><title lang=\"id\">{$title}</title><desc>Deskripsi {$title}</desc></programme>";

    return '<?xml version="1.0" encoding="UTF-8"?><tv>'
        .'<channel id="Metro TV.id"><display-name>Metro TV</display-name></channel>'
        .'<channel id="TVRI.id"><display-name>TVRI</display-name></channel>'
        .$p('Metro TV.id', $now->copy()->subMinutes(30), $now->copy()->addMinutes(30), 'Metro Hari Ini')
        .$p('Metro TV.id', $now->copy()->addMinutes(30), $now->copy()->addMinutes(90), 'Primetime News')
        .$p('Metro TV.id', $now->copy()->subDays(2), $now->copy()->subDays(2)->addHour(), 'Basi')
        .$p('TVRI.id', $now->copy()->subMinutes(10), $now->copy()->addMinutes(50), 'Klik Indonesia')
        .'</tv>';
}

it('imports XMLTV (plain and gzip), auto-matches by name and serves the guide', function () {
    Http::fake([
        // Closures: every request gets a fresh body (a static fake stream can only be read once).
        'a.example/*' => fn () => Http::response(gzencode(xmltv())),
        'b.example/*' => fn () => Http::response('<tv></tv>'),
    ]);
    HotelProfile::current()->update(['epg_urls' => "https://a.example/id.xml.gz\nhttps://b.example/x.xml"]);
    $metro = Channel::factory()->create(['number' => 103, 'name' => 'Metro TV HD', 'epg_id' => null]);
    $tvri = Channel::factory()->create(['number' => 101, 'name' => 'TVRI Nasional', 'epg_id' => 'tvri.id']); // case-insensitive id
    Channel::factory()->create(['number' => 999, 'name' => 'Tanpa EPG']);

    $first = app(EpgImporter::class)->sync();
    expect($first['channels'])->toBe(1) // only TVRI had an id
        ->and(EpgImporter::catalog())->toHaveKeys(['Metro TV.id', 'TVRI.id'])
        ->and(EpgImporter::autoMatch())->toBe(1)
        ->and($metro->fresh()->epg_id)->toBe('Metro TV.id');

    $this->artisan('sangatv:sync-epg')->assertSuccessful();
    expect(EpgProgramme::query()->where('channel_id', $metro->id)->pluck('title')->all())->toBe(['Metro Hari Ini', 'Primetime News'])
        ->and(HotelProfile::current()->epg_status)->toContain('2/2 channel terisi');

    tvFor(Room::factory()->create());
    $res = $this->getJson('/api/v1/epg')->assertOk();
    expect($res->json("programmes.{$metro->id}.0.title"))->toBe('Metro Hari Ini')
        ->and($res->json("programmes.{$tvri->id}.0.description"))->toBe('Deskripsi Klik Indonesia');
    $epg = collect($this->getJson('/api/v1/channels')->assertOk()->json('channels'))->pluck('has_epg', 'number');
    expect($epg->all())->toMatchArray([103 => true, 101 => true, 999 => false]);
});

it('filters channels by room-type package and adult PIN, and orders by guest language', function () {
    $suite = Room::factory()->create(['type' => 'Suite']);
    $deluxe = Room::factory()->create(['type' => 'Deluxe']);
    Channel::factory()->create(['number' => 101, 'sort_order' => 101, 'name' => 'Umum', 'room_types' => null]);
    Channel::factory()->create(['number' => 102, 'sort_order' => 102, 'name' => 'Umum2', 'room_types' => []]);
    Channel::factory()->create(['number' => 301, 'sort_order' => 301, 'name' => 'Premium', 'room_types' => ['Suite']]);
    Channel::factory()->create(['number' => 401, 'sort_order' => 401, 'name' => 'Dewasa', 'is_adult' => true]);
    Channel::factory()->create(['number' => 501, 'sort_order' => 501, 'name' => 'CCTV4', 'language' => 'zh']);

    tvFor($deluxe);
    $names = fn () => collect($this->getJson('/api/v1/channels')->assertOk()->json('channels'))->pluck('name')->all();
    expect($names())->toBe(['Umum', 'Umum2', 'CCTV4']); // no Suite package, no adult without PIN

    HotelProfile::current()->update(['adult_pin' => HotelProfile::hashPin('2468')]);
    Guest::factory()->create(['room_id' => $suite->id, 'language' => 'zh']); // before tvFor: audit logging touches the auth guard
    tvFor($suite);
    $list = $this->getJson('/api/v1/channels')->assertOk()->json('channels');
    expect(collect($list)->pluck('name')->all())->toBe(['CCTV4', 'Umum', 'Umum2', 'Premium', 'Dewasa'])
        ->and(collect($list)->pluck('sort_order')->all())->toBe([0, 1, 2, 3, 4])
        ->and(collect($list)->firstWhere('name', 'Dewasa')['is_adult'])->toBeTrue();

    $this->getJson('/api/v1/bootstrap')->assertOk()->assertJsonPath('adult_pin.algo', 'pbkdf2-sha256');
});

it('stores guest favourites on the stay and drops them at check-out', function () {
    $room = Room::factory()->create();
    tvFor($room);
    $a = Channel::factory()->create(['number' => 1]);
    $b = Channel::factory()->create(['number' => 2]);

    $this->putJson('/api/v1/favorites', ['channel_ids' => [$b->id]])->assertStatus(409);

    $guest = Guest::factory()->create(['room_id' => $room->id]);
    $this->putJson('/api/v1/favorites', ['channel_ids' => [$b->id, $a->id, 99999, $b->id]])->assertOk()->assertJsonPath('favorites', [$b->id, $a->id]);
    $this->getJson('/api/v1/bootstrap')->assertJsonPath('favorites', [$b->id, $a->id]);

    $guest->update(['status' => 'checked_out', 'checked_out_at' => now()]);
    Guest::factory()->create(['room_id' => $room->id]);
    $this->getJson('/api/v1/bootstrap')->assertJsonPath('favorites', []);
});

it('records viewing sessions per room and builds statistics', function () {
    $room = Room::factory()->create();
    tvFor($room);
    $a = Channel::factory()->create(['number' => 1, 'name' => 'Alpha']);
    $b = Channel::factory()->create(['number' => 2, 'name' => 'Beta']);
    Channel::factory()->create(['number' => 3, 'name' => 'Sepi']);

    $this->postJson('/api/v1/views', ['views' => [
        ['channel_id' => $a->id, 'started_at' => now()->subHour()->toIso8601String(), 'seconds' => 1800],
        ['channel_id' => $b->id, 'started_at' => now()->subMinutes(20)->toIso8601String(), 'seconds' => 600],
        ['channel_id' => $b->id, 'started_at' => now()->toIso8601String(), 'seconds' => 4], // zapping
        ['channel_id' => $a->id, 'started_at' => now()->subDays(30)->toIso8601String(), 'seconds' => 900], // too old
        ['channel_id' => 99999, 'started_at' => now()->toIso8601String(), 'seconds' => 900],
    ]])->assertOk()->assertJsonPath('stored', 2);

    expect(ChannelView::query()->where('room_id', $room->id)->count())->toBe(2);
    $stats = new ChannelStats(7);
    expect($stats->totals())->toMatchArray(['hours' => 0.7, 'sessions' => 2, 'rooms' => 1])
        ->and($stats->topChannels()->pluck('channel.name')->all())->toBe(['Alpha', 'Beta'])
        ->and($stats->topChannels()->first()['share'])->toBe(75.0)
        ->and(array_sum($stats->byHour()))->toBeGreaterThan(0.6)
        ->and($stats->unwatched()->pluck('name')->all())->toBe(['Sepi']);
});

it('computes uptime and mails the weekly report', function () {
    Mail::fake();
    $c = Channel::factory()->create(['number' => 1, 'name' => 'Alpha']);
    foreach (['ok', 'ok', 'ok', 'error'] as $i => $st) {
        ChannelCheck::query()->create(['channel_id' => $c->id, 'status' => $st, 'message' => $st === 'error' ? 'HTTP 503' : null, 'checked_at' => now()->subMinutes(10 * $i)]);
    }
    $row = (new ChannelStats(7))->uptime()->first();
    expect($row['uptime'])->toBe(75.0)->and($row['failed_checks'])->toBe(1)->and($row['last_error'])->toContain('HTTP 503');

    $this->artisan('sangatv:uptime-report')->expectsOutputToContain('Belum ada email')->assertSuccessful();
    HotelProfile::current()->update(['report_emails' => 'it@hotel.test, salah, gm@hotel.test']);
    $this->artisan('sangatv:uptime-report')->assertSuccessful();
    Mail::assertSent(ChannelUptimeReport::class, fn ($m) => $m->hasTo('it@hotel.test') && $m->hasTo('gm@hotel.test'));
    expect((new ChannelUptimeReport)->render())->toContain('75%')->toContain('Alpha');
});

it('delivers live announcements to targeted rooms via heartbeat', function () {
    $f2 = Room::factory()->create(['floor' => 2]);
    $f3 = Room::factory()->create(['floor' => 3]);
    $ch = Channel::factory()->create(['number' => 900]);
    Announcement::query()->create(['title' => 'Semua', 'message' => 'Halo', 'force_channel_id' => $ch->id, 'level' => 'emergency', 'style' => 'fullscreen']);
    Announcement::query()->create(['title' => 'Lantai 3', 'message' => 'Air mati', 'floors' => [3]]);
    Announcement::query()->create(['title' => 'Kamar F2', 'message' => 'Pesan', 'room_ids' => [$f2->id]]);
    Announcement::query()->create(['title' => 'Nanti', 'message' => 'x', 'starts_at' => now()->addHour()]);
    Announcement::query()->create(['title' => 'Lewat', 'message' => 'x', 'ends_at' => now()->subMinute()]);
    Announcement::query()->create(['title' => 'Mati', 'message' => 'x', 'is_active' => false]);

    tvFor($f2);
    $res = $this->postJson('/api/v1/heartbeat', [])->assertOk();
    expect(collect($res->json('announcements'))->pluck('title')->sort()->values()->all())->toBe(['Kamar F2', 'Semua'])
        ->and(collect($res->json('announcements'))->firstWhere('title', 'Semua')['force_channel_number'])->toBe(900);

    tvFor($f3);
    expect(collect($this->postJson('/api/v1/heartbeat', [])->json('announcements'))->pluck('title')->sort()->values()->all())->toBe(['Lantai 3', 'Semua']);
});

it('lets front office create and stop announcements in the CMS', function () {
    $u = User::factory()->create(['role' => UserRole::FrontOffice]);
    $this->actingAs($u);
    $room = Room::factory()->create(['floor' => 4]);

    Livewire::test(ManageAnnouncements::class)
        ->callAction('create', data: ['title' => 'Kolam renang tutup', 'message' => 'Perawatan pukul 14.00', 'style' => 'ticker', 'level' => 'info', 'target' => 'floors', 'floors' => [4], 'is_active' => true])
        ->assertHasNoActionErrors();

    $a = Announcement::query()->sole();
    expect($a->floors)->toBe([4])->and($a->room_ids)->toBeNull()->and($a->created_by)->toBe($u->id)->and($a->targets($room))->toBeTrue();

    Livewire::test(ManageAnnouncements::class)->callAction(TestAction::make('stop')->table($a));
    expect($a->fresh()->state())->toBe('off');
});

it('saves EPG sources, adult PIN and report e-mails from the hotel profile', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));

    Livewire::test(HotelProfilePage::class)
        ->fillForm(['epg_urls' => 'https://www.open-epg.com/files/indonesia1.xml', 'new_adult_pin' => '12', 'report_emails' => 'it@hotel.test'])
        ->call('save')->assertHasFormErrors(['new_adult_pin']);

    Livewire::test(HotelProfilePage::class)
        ->fillForm(['epg_urls' => 'https://www.open-epg.com/files/indonesia1.xml', 'new_adult_pin' => '2468', 'report_emails' => 'it@hotel.test'])
        ->call('save')->assertHasNoFormErrors();
    $h = HotelProfile::current();
    expect($h->epgUrlList())->toBe(['https://www.open-epg.com/files/indonesia1.xml'])
        ->and(HotelProfile::pinMatches($h->adult_pin, '2468'))->toBeTrue()
        ->and($h->reportEmailList())->toBe(['it@hotel.test']);

    Livewire::test(HotelProfilePage::class)->fillForm(['new_adult_pin' => 'hapus'])->call('save')->assertHasNoFormErrors();
    expect(HotelProfile::current()->adult_pin)->toBeNull();
});

it('runs EPG sync from the channel page and renders the stats page', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    Http::fake(['a.example/*' => fn () => Http::response(xmltv())]);
    HotelProfile::current()->update(['epg_urls' => 'https://a.example/id.xml']);
    $c = Channel::factory()->create(['number' => 103, 'name' => 'Metro TV']);

    Livewire::test(ManageChannels::class)->callAction('syncEpg', data: ['auto_match' => true])->assertNotified();
    expect($c->fresh()->epg_id)->toBe('Metro TV.id')->and(EpgProgramme::query()->count())->toBe(2);

    ChannelView::query()->create(['channel_id' => $c->id, 'started_at' => now()->subHour(), 'seconds' => 3600]);
    Livewire::test(ChannelStatsPage::class)->assertSee('Metro TV')->set('days', 30)->assertSee('1,0 jam');
});

it('uses one slide as the Apps-screen background', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $a = Slide::query()->create(['title' => 'A', 'image_path' => 'https://cdn.example/a.jpg', 'is_apps_background' => true]);
    $b = Slide::query()->create(['title' => 'B', 'image_path' => 'https://cdn.example/b.jpg']);

    expect(Slide::appsBackgroundUrl())->toBe('https://cdn.example/a.jpg');

    // Toggle in the CMS table: B becomes the background, A is switched off.
    Livewire::test(ManageSlides::class)
        ->call('updateTableColumnState', 'is_apps_background', (string) $b->id, true);
    expect($b->fresh()->is_apps_background)->toBeTrue()->and($a->fresh()->is_apps_background)->toBeFalse();

    tvFor(Room::factory()->create());
    $this->getJson('/api/v1/bootstrap')->assertOk()->assertJsonPath('apps_background_url', 'https://cdn.example/b.jpg');

    $b->update(['is_apps_background' => false]);
    expect(Slide::appsBackgroundUrl())->toBeNull();
});

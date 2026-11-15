<?php

use App\Enums\UserRole;
use App\Filament\Pages\PmsIntegrationPage;
use App\Filament\Resources\PmsIntegrations\Pages\ManagePmsIntegrations;
use App\Filament\Resources\PmsIntegrations\PmsIntegrationResource;
use App\Models\HotelProfile;
use App\Models\PmsIntegration;
use App\Models\User;
use Livewire\Livewire;

function asRole(UserRole $role): User
{
    $u = User::factory()->create(['role' => $role]);
    test()->actingAs($u);

    return $u;
}

it('shows the PMS pages to admin and IT only', function () {
    asRole(UserRole::It);
    foreach (['/admin/integrasi-pms', '/admin/pms-kunci-api', '/admin/pms-log'] as $url) {
        $this->get($url)->assertOk();
    }
    $this->get('/admin/integrasi-pms')->assertSee('Pertanyaan untuk vendor PMS')->assertSee('/api/pms/v1')->assertSee('X-SangaTV-Signature');

    asRole(UserRole::FrontOffice);
    $this->get('/admin/integrasi-pms')->assertForbidden();
    $this->get('/admin/pms-kunci-api')->assertForbidden();
    $this->get('/admin/integrasi-pms/dokumen-vendor.md')->assertForbidden();
});

it('auto-saves the vendor checklist', function () {
    asRole(UserRole::Admin);

    Livewire::test(PmsIntegrationPage::class)
        ->set('checklist.rest.done', true)
        ->set('checklist.rest.note', 'VHP bisa webhook REST, kontak Pak Andi');

    $saved = HotelProfile::current()->pms_checklist;
    expect($saved['rest'])->toBe(['done' => true, 'note' => 'VHP bisa webhook REST, kontak Pak Andi'])
        ->and($saved)->toHaveKey('sandbox');
});

it('creates an API key, shows the secret once, and the key really works', function () {
    asRole(UserRole::It);

    Livewire::test(ManagePmsIntegrations::class)
        ->callAction('create', ['name' => 'VHP', 'auth_mode' => 'hmac', 'allowed_ips' => [], 'auto_create_rooms' => false, 'is_active' => true])
        ->assertHasNoActionErrors()
        ->assertNotified('Kunci API dibuat');

    $p = PmsIntegration::firstWhere('name', 'VHP');
    expect($p->key_id)->toStartWith('pms_')
        ->and(DB::table('pms_integrations')->value('secret'))->not->toBe($p->secret); // encrypted at rest

    [$status, $body] = PmsIntegrationResource::testConnection($p);
    expect($status)->toBe(200)->and($body['ok'])->toBeTrue();
});

it('requires an IP whitelist for bearer mode', function () {
    asRole(UserRole::It);

    Livewire::test(ManagePmsIntegrations::class)
        ->callAction('create', ['name' => 'X', 'auth_mode' => 'bearer', 'allowed_ips' => [], 'is_active' => true])
        ->assertHasActionErrors(['allowed_ips']);
});

it('downloads the vendor document with the production base URL', function () {
    asRole(UserRole::It);
    config(['app.url' => 'https://sangatvplus.example']);

    $res = $this->get('/admin/integrasi-pms/dokumen-vendor.md')->assertOk()->assertHeader('Content-Type', 'text/markdown; charset=UTF-8');
    expect($res->getContent())->toContain('https://sangatvplus.example/api/pms/v1')->toContain('POST /checkins');
});

it('redirects guests to the CMS login for the vendor document', function () {
    $this->get('/admin/integrasi-pms/dokumen-vendor.md')->assertRedirect(route('filament.admin.auth.login'));
});

<?php

use App\Enums\UserRole;
use App\Filament\Resources\Gallery\Pages\ManageGallery;
use App\Models\Device;
use App\Models\GalleryPhoto;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(fn () => Storage::fake('public'));

it('bulk uploads photos into an album with thumbnails', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::FrontOffice]));

    Livewire::test(ManageGallery::class)
        ->callAction('bulkUpload', [
            'album' => 'Kolam Renang',
            'photos' => [UploadedFile::fake()->image('a.jpg', 1600, 900), UploadedFile::fake()->image('b.png', 1200, 800)],
        ])
        ->assertHasNoActionErrors()
        ->assertNotified('2 foto ditambahkan');

    $photos = GalleryPhoto::orderBy('sort_order')->get();
    expect($photos)->toHaveCount(2)->and($photos->first()->album)->toBe('Kolam Renang');
    foreach ($photos as $p) {
        expect(Storage::disk('public')->exists($p->image_path))->toBeTrue()
            ->and($p->thumb_path)->toEndWith('-thumb.jpg');
        [$w] = getimagesizefromstring(Storage::disk('public')->get($p->thumb_path));
        expect($w)->toBeLessThanOrEqual(480);
    }
});

it('renders the gallery page for front office', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::FrontOffice]));
    GalleryPhoto::create(['title' => 'Lobi', 'image_path' => UploadedFile::fake()->image('l.jpg', 800, 600)->store('gallery', 'public')]);

    $this->get('/admin/galeri')->assertOk()->assertSee('Lobi');
});

it('sends visible gallery photos to the TV and bumps content_version', function () {
    $device = Device::factory()->create();
    $this->withToken($device->createToken('tv', ['tv'])->plainTextToken);
    $v1 = $this->postJson('/api/v1/heartbeat')->json('content_version');

    $this->travel(2)->seconds();
    GalleryPhoto::create(['album' => 'Kamar', 'title' => 'Deluxe', 'image_path' => UploadedFile::fake()->image('k.jpg', 1000, 700)->store('gallery', 'public')]);
    GalleryPhoto::create(['title' => 'Draft', 'is_active' => false, 'image_path' => UploadedFile::fake()->image('d.jpg', 100, 100)->store('gallery', 'public')]);

    $res = $this->getJson('/api/v1/facilities')->assertOk()->assertJsonCount(1, 'gallery')
        ->assertJsonPath('gallery.0.title', 'Deluxe')->assertJsonPath('gallery.0.album', 'Kamar');
    expect($res->json('gallery.0.thumb_url'))->toContain('-thumb.jpg')
        ->and($this->postJson('/api/v1/heartbeat')->json('content_version'))->not->toBe($v1);
});

it('removes files when a photo is deleted', function () {
    $p = GalleryPhoto::create(['image_path' => UploadedFile::fake()->image('x.jpg', 900, 600)->store('gallery', 'public')]);
    $files = [$p->image_path, $p->thumb_path];
    $p->delete();
    foreach ($files as $f) {
        expect(Storage::disk('public')->exists($f))->toBeFalse();
    }
});

<?php

namespace App\Providers;

use App\Models\Announcement;
use App\Models\AppRelease;
use App\Models\AppWhitelist;
use App\Models\Channel;
use App\Models\ChannelCategory;
use App\Models\ChannelCheck;
use App\Models\ChannelProvider;
use App\Models\ChannelReport;
use App\Models\ChannelView;
use App\Models\Device;
use App\Models\DeviceCommand;
use App\Models\EpgProgramme;
use App\Models\Facility;
use App\Models\GalleryPhoto;
use App\Models\Guest;
use App\Models\HotelProfile;
use App\Models\PmsEvent;
use App\Models\PmsIntegration;
use App\Models\ProvisioningCode;
use App\Models\Room;
use App\Models\Slide;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Schemas\Components\Section;
use Filament\Support\Facades\FilamentTimezone;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        // Timestamps are stored in UTC; the CMS shows them in the hotel's timezone (WIB/WITA/WIT).
        FilamentTimezone::set(function (): string {
            static $tz = null;

            return $tz ??= rescue(fn () => HotelProfile::query()->value('timezone'), null, false) ?: 'Asia/Jakarta';
        });

        // CMS look: dense, striped tables and compact sections everywhere.
        Table::configureUsing(fn (Table $table) => $table
            ->striped()
            ->paginationPageOptions([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->emptyStateIcon(Heroicon::OutlinedSparkles));
        Section::configureUsing(fn (Section $section) => $section->compact());

        // Row actions as compact icon buttons with tooltips.
        EditAction::configureUsing(fn (EditAction $action) => $action->iconButton()->icon(Heroicon::OutlinedPencilSquare)->tooltip('Ubah'));
        DeleteAction::configureUsing(fn (DeleteAction $action) => $action->iconButton()->icon(Heroicon::OutlinedTrash)->tooltip('Hapus'));
        ViewAction::configureUsing(fn (ViewAction $action) => $action->iconButton()->icon(Heroicon::OutlinedEye)->tooltip('Lihat'));

        // Short, stable names in polymorphic columns (audit_logs, tokens).
        Relation::enforceMorphMap([
            'user' => User::class,
            'device' => Device::class,
            'room' => Room::class,
            'guest' => Guest::class,
            'channel' => Channel::class,
            'channel_category' => ChannelCategory::class,
            'channel_provider' => ChannelProvider::class,
            'slide' => Slide::class,
            'facility' => Facility::class,
            'app_whitelist' => AppWhitelist::class,
            'app_release' => AppRelease::class,
            'hotel_profile' => HotelProfile::class,
            'device_command' => DeviceCommand::class,
            'provisioning_code' => ProvisioningCode::class,
            'pms_integration' => PmsIntegration::class,
            'pms_event' => PmsEvent::class,
            'gallery_photo' => GalleryPhoto::class,
            'channel_report' => ChannelReport::class,
            'announcement' => Announcement::class,
            'epg_programme' => EpgProgramme::class,
            'channel_view' => ChannelView::class,
            'channel_check' => ChannelCheck::class,
        ]);

        // Per-device API budget (≈2 req/s sustained is far above normal use).
        RateLimiter::for('device-api', fn (Request $r) => Limit::perMinute(120)->by('dev:'.($r->user()?->getKey() ?? $r->ip())));

        // PMS integration: generous (bulk sync), per integration key.
        RateLimiter::for('pms', fn (Request $r) => Limit::perMinute(600)->by('pms:'.($r->header('X-SangaTV-Key') ?: $r->ip())));

        // Provisioning is unauthenticated: keep it tight per IP.
        RateLimiter::for('provision', fn (Request $r) => Limit::perMinute(5)->by('prov:'.$r->ip()));
    }
}

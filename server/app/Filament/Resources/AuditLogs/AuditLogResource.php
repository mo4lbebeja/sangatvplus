<?php

namespace App\Filament\Resources\AuditLogs;

use App\Enums\UserRole;
use App\Filament\Concerns\RestrictedByRole;
use App\Filament\Resources\AuditLogs\Pages\ManageAuditLogs;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\User;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;
use UnitEnum;

class AuditLogResource extends Resource
{
    use RestrictedByRole;

    protected static ?string $model = AuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Sistem';

    protected static ?string $modelLabel = 'log';

    protected static ?string $pluralModelLabel = 'Audit Log';

    protected static ?int $navigationSort = 20;

    protected static function viewRoles(): array
    {
        return [UserRole::It];
    }

    // Audit log is append-only.
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function actorLabel(AuditLog $log): string
    {
        return match (true) {
            $log->actor instanceof User => $log->actor->name,
            $log->actor instanceof Device => 'TV kamar '.($log->actor->room?->number ?? '?'),
            default => 'Sistem',
        };
    }

    private const SUBJECTS = [
        'user' => 'Pengguna', 'vendor_user' => 'Tim Sijalucom', 'device' => 'TV', 'room' => 'Kamar', 'guest' => 'Tamu',
        'channel' => 'Channel', 'channel_category' => 'Kategori channel', 'channel_provider' => 'Penyedia channel', 'slide' => 'Slide',
        'facility' => 'Fasilitas', 'app_whitelist' => 'Aplikasi diizinkan', 'app_release' => 'Rilis aplikasi', 'hotel_profile' => 'Profil hotel',
        'device_command' => 'Perintah TV', 'provisioning_code' => 'Kode daftar TV', 'pms_integration' => 'Kunci API PMS', 'pms_event' => 'Kiriman PMS',
        'gallery_photo' => 'Foto galeri', 'channel_report' => 'Laporan channel', 'announcement' => 'Pengumuman',
    ];

    private const EVENTS = [
        'app_release.published' => 'Rilis aplikasi diterbitkan', 'app_release.push_update' => 'Semua TV diminta update',
        'channel.imported' => 'Channel diimpor', 'device.command_queued' => 'Perintah dikirim ke TV', 'device.command_done' => 'Perintah TV selesai',
        'device.command_failed' => 'Perintah TV gagal', 'device.provision_failed' => 'Pendaftaran TV gagal', 'device.provisioned' => 'TV didaftarkan',
        'device.revoked' => 'Akses TV dicabut', 'device.update_queued' => 'TV diminta update', 'guest.checked_out' => 'Tamu check-out', 'guest.moved' => 'Tamu pindah kamar',
        'home_music.updated' => 'Musik latar diubah', 'hotel.data_exported' => 'Data hotel diekspor', 'pms.secret_rotated' => 'Kunci API PMS diganti',
        'provisioning_code.issued' => 'Kode daftar TV dibuat', 'service.period_set' => 'Masa layanan diatur', 'service.released' => 'Layanan dilepas',
        'service.restored' => 'Layanan dipulihkan', 'technician.pin_changed' => 'PIN teknisi diganti', 'technician.pin_failed' => 'PIN teknisi salah',
        'technician.pin_failed_offline' => 'PIN teknisi salah (offline)', 'technician.pin_locked' => 'PIN teknisi terkunci', 'technician.pin_ok' => 'Teknisi masuk',
        'technician.pin_ok_offline' => 'Teknisi masuk (offline)', 'vendor.masuk_sebagai' => 'Sijalucom masuk ke CMS',
    ];

    /** "Channel.updated" → "Channel diubah"; unknown events stay readable. */
    public static function eventLabel(string $event): string
    {
        if (isset(self::EVENTS[$event])) {
            return self::EVENTS[$event];
        }
        [$subject, $verb] = explode('.', $event, 2) + [1 => ''];
        $done = ['created' => 'dibuat', 'updated' => 'diubah', 'deleted' => 'dihapus'][$verb] ?? null;
        if ($done) {
            return (self::SUBJECTS[Str::snake($subject)] ?? Str::headline($subject)).' '.$done;
        }

        return ($subject === 'technician' ? 'Teknisi: ' : Str::headline($subject).': ').str_replace('_', ' ', $verb);
    }

    public static function subjectLabel(AuditLog $log): ?string
    {
        return $log->subject_type ? (self::SUBJECTS[$log->subject_type] ?? Str::headline($log->subject_type)).' #'.$log->subject_id : null;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('created_at')->label('Waktu')->dateTime('d M Y H:i:s'),
            TextEntry::make('event')->label('Kejadian')->formatStateUsing(fn (string $state) => static::eventLabel($state)),
            TextEntry::make('actor')->label('Pelaku')->state(fn (AuditLog $record) => static::actorLabel($record)),
            TextEntry::make('subject')->label('Objek')->state(fn (AuditLog $record) => static::subjectLabel($record) ?? '—'),
            TextEntry::make('ip')->label('IP')->placeholder('—'),
            KeyValueEntry::make('properties')->label('Detail')->columnSpanFull()
                ->state(fn (AuditLog $record) => collect($record->properties ?? [])->map(fn ($v) => is_scalar($v) || $v === null ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE))->all()),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['actor' => fn (MorphTo $m) => $m->morphWith([Device::class => ['room']])]))
            ->columns([
                TextColumn::make('created_at')->label('Waktu')->dateTime('d M Y H:i:s')->sortable()->icon(Heroicon::OutlinedClock)->color('gray'),
                TextColumn::make('event')->label('Kejadian')->searchable()->badge()
                    ->formatStateUsing(fn (string $state) => static::eventLabel($state))
                    ->color(fn (string $state) => str_contains($state, 'failed') || str_contains($state, 'locked') || str_contains($state, 'revoked') ? 'danger' : 'gray'),
                TextColumn::make('actor_label')->label('Pelaku')->state(fn (AuditLog $record) => static::actorLabel($record))->icon(fn (AuditLog $record) => $record->actor instanceof Device ? Heroicon::OutlinedTv : ($record->actor ? Heroicon::OutlinedUser : Heroicon::OutlinedCog6Tooth)),
                TextColumn::make('subject_type')->label('Objek')->formatStateUsing(fn ($state, AuditLog $record) => static::subjectLabel($record))->placeholder('—'),
                TextColumn::make('ip')->label('IP')->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Filter::make('security')->label('Hanya event keamanan')
                    ->query(fn (Builder $query) => $query->where(fn ($q) => $q->where('event', 'like', 'technician.%')->orWhere('event', 'like', 'device.%')->orWhere('event', 'like', '%.failed'))),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function getNavigationBadge(): ?string
    {
        $n = AuditLog::query()->where('created_at', '>', now()->subDay())
            ->where(fn ($q) => $q->where('event', 'like', '%failed%')->orWhere('event', 'like', '%locked%')->orWhere('event', 'like', '%revoked%'))
            ->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Event keamanan 24 jam terakhir';
    }

    public static function getPages(): array
    {
        return ['index' => ManageAuditLogs::route('/')];
    }
}

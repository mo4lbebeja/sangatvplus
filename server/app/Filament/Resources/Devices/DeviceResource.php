<?php

namespace App\Filament\Resources\Devices;

use App\Enums\CommandStatus;
use App\Enums\CommandType;
use App\Enums\DeviceStatus;
use App\Enums\UserRole;
use App\Filament\Concerns\RestrictedByRole;
use App\Filament\Resources\Devices\Pages\ManageDevices;
use App\Models\AppWhitelist;
use App\Models\Device;
use App\Support\Audit;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class DeviceResource extends Resource
{
    use RestrictedByRole;

    protected static ?string $model = Device::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTv;

    protected static string|UnitEnum|null $navigationGroup = 'Perangkat';

    protected static ?string $modelLabel = 'TV';

    protected static ?string $pluralModelLabel = 'TV Terdaftar';

    protected static ?int $navigationSort = 10;

    protected static function viewRoles(): array
    {
        return [UserRole::It];
    }

    /** TVs are only added through provisioning codes. */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('room_id')->label('Kamar')->relationship('room', 'number')->searchable()->preload()->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('room.number')->label('Kamar')->sortable()->searchable()->icon(Heroicon::OutlinedHomeModern)->iconColor('primary')->weight('bold'),
                TextColumn::make('model')->label('Model')->searchable()->toggleable()->icon(Heroicon::OutlinedTv),
                TextColumn::make('android_version')->label('Android')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('device_uid')->label('ID perangkat')->limit(14)->copyable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')->badge(),
                TextColumn::make('power')->label('Status')->badge()
                    ->state(fn (Device $r) => $r->powerState())
                    ->formatStateUsing(fn (string $state) => ['on' => 'Menyala', 'standby' => 'Standby', 'off' => 'Mati'][$state])
                    ->color(fn (string $state) => $state === 'on' ? 'success' : 'danger')
                    ->icon(fn (string $state) => match ($state) {
                        'on' => Heroicon::OutlinedSignal,
                        'standby' => Heroicon::OutlinedMoon,
                        default => Heroicon::OutlinedSignalSlash,
                    })
                    ->tooltip(fn (Device $r) => match ($r->powerState()) {
                        'on' => 'Layar menyala'.($r->screen_changed_at ? ' sejak '.$r->screen_changed_at->diffForHumans() : ''),
                        'standby' => 'Terhubung, layar mati'.($r->screen_changed_at ? ' sejak '.$r->screen_changed_at->diffForHumans() : ''),
                        default => $r->last_seen_at ? 'Tidak ada sinyal sejak '.$r->last_seen_at->diffForHumans() : 'Belum pernah terhubung',
                    }),
                TextColumn::make('service_released')->label('Mode')->badge()
                    ->state(fn (Device $r) => $r->service_released ? 'released' : 'hotel')
                    ->formatStateUsing(fn (string $state) => $state === 'released' ? 'Dilepas' : 'Layar hotel')
                    ->color(fn (string $state) => $state === 'released' ? 'warning' : 'gray')
                    ->tooltip(fn (Device $r) => $r->service_released ? 'TV sudah kembali ke layar utama bawaan (layanan dilepas / tidak terhubung)' : 'SangaTV Plus menjadi layar utama')
                    ->toggleable(),
                ToggleColumn::make('hold_updates')->label('Tahan update')
                    ->tooltip('Aktifkan bila tidak ada orang di dekat TV: TV tidak menerima update (dialog pasang tidak muncul) sampai dimatikan lagi.')
                    ->toggleable(),
                IconColumn::make('autostart')->label('Mulai otomatis')
                    ->state(fn (Device $r) => $r->autoStartState() ?? 'unknown')
                    ->icon(fn (string $state) => match ($state) {
                        'ok' => Heroicon::OutlinedCheckCircle,
                        'partial' => Heroicon::OutlinedClock,
                        'no' => Heroicon::OutlinedXCircle,
                        default => Heroicon::OutlinedQuestionMarkCircle,
                    })
                    ->color(fn (string $state) => match ($state) {
                        'ok' => 'success', 'partial' => 'warning', 'no' => 'danger', default => 'gray',
                    })
                    ->tooltip(fn (Device $r) => match ($r->autoStartState()) {
                        'ok' => 'Langsung tampil saat TV dinyalakan',
                        'partial' => 'Launcher bawaan tampil dulu, lalu SangaTV Plus (jalankan skrip siapkan-tv)',
                        'no' => 'Belum diatur: jalankan skrip siapkan-tv',
                        default => 'Belum dilaporkan TV',
                    }),
                IconColumn::make('kiosk_enabled')->label('Kiosk')->boolean()->trueIcon(Heroicon::OutlinedLockClosed)->falseIcon(Heroicon::OutlinedLockOpen),
                IconColumn::make('device_owner')->label('Owner')->boolean()->toggleable(),
                TextColumn::make('app_version')->label('Versi')->badge()->icon(Heroicon::OutlinedCodeBracket)
                    ->color(fn (Device $r) => $r->availableRelease() ? 'warning' : 'success')
                    ->description(fn (Device $r) => static::updateNote($r)),
                TextColumn::make('last_seen_at')->label('Terakhir aktif')->since()->sortable()->icon(Heroicon::OutlinedClock)->color('gray'),
                TextColumn::make('last_ip')->label('IP')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('last_seen_at', 'desc')
            ->poll('30s')
            ->filters([
                SelectFilter::make('status')->label('Akses')->options(DeviceStatus::class),
                SelectFilter::make('power')->label('Status layar')
                    ->options(['on' => 'Menyala', 'standby' => 'Standby', 'off' => 'Mati'])
                    ->query(function ($query, array $data) {
                        $t = Device::onlineThreshold();

                        return match ($data['value'] ?? null) {
                            'on' => $query->where('last_seen_at', '>', $t)->where(fn ($q) => $q->whereNull('screen_on')->orWhere('screen_on', true)),
                            'standby' => $query->where('last_seen_at', '>', $t)->where('screen_on', false),
                            'off' => $query->where(fn ($q) => $q->whereNull('last_seen_at')->orWhere('last_seen_at', '<=', $t)),
                            default => $query,
                        };
                    }),
            ])
            ->toolbarActions([
                BulkAction::make('updateSelected')->label('Update TV terpilih')->icon(Heroicon::OutlinedArrowDownTray)->color('primary')
                    ->visible(fn () => static::canViewAny())
                    ->requiresConfirmation()->modalHeading('Update TV terpilih sekarang?')->modalDescription(static::updateWarning())
                    ->deselectRecordsAfterCompletion()
                    ->action(fn (Collection $records) => static::sendUpdates($records)),
            ])
            ->recordActions([
                Action::make('updateNow')->label('Update sekarang')->icon(Heroicon::OutlinedArrowDownTray)->color('warning')
                    ->iconButton()
                    ->tooltip(fn (Device $r) => match (true) {
                        $r->hold_updates => 'Matikan "Tahan update" dulu',
                        $r->updateQueued() => 'Perintah update sedang dikirim ke TV',
                        default => 'Update ke versi '.$r->availableRelease()?->version_name.' sekarang',
                    })
                    ->visible(fn (Device $r) => $r->status === DeviceStatus::Active && static::canEdit($r) && $r->availableRelease())
                    ->disabled(fn (Device $r) => $r->hold_updates || $r->updateQueued())
                    ->requiresConfirmation()
                    ->modalHeading(fn (Device $r) => 'Update TV kamar '.($r->room?->number ?? '?').' ke versi '.$r->availableRelease()?->version_name.'?')
                    ->modalDescription(static::updateWarning())
                    ->modalSubmitActionLabel('Update sekarang')
                    ->action(fn (Device $record) => static::sendUpdates(collect([$record]))),
                Action::make('command')
                    ->label('Kirim perintah')
                    ->icon(Heroicon::OutlinedCommandLine)
                    ->iconButton()->tooltip('Kirim perintah')->color('primary')
                    ->visible(fn (Device $r) => $r->status === DeviceStatus::Active && static::canEdit($r))
                    ->schema(fn (Device $record) => [
                        Select::make('type')->label('Perintah')->required()
                            ->options(collect(CommandType::cases())
                                ->reject(fn (CommandType $t) => $t->requiresDeviceOwner() && ! $record->device_owner)
                                ->mapWithKeys(fn (CommandType $t) => [$t->value => $t->getLabel()]))
                            ->helperText($record->device_owner ? null : 'Restart TV & sembunyikan launcher hanya tersedia untuk TV Device Owner (aturan Android). TV ini bukan Device Owner.'),
                    ])
                    ->action(function (Device $record, array $data) {
                        $type = $data['type'] instanceof CommandType ? $data['type'] : CommandType::from($data['type']);
                        if ($type->requiresDeviceOwner() && ! $record->device_owner) {
                            Notification::make()->title('Perintah tidak bisa dikirim')->body('"'.$type->getLabel().'" hanya untuk TV Device Owner.')->danger()->send();

                            return;
                        }
                        $payload = $type === CommandType::ClearAppData
                            ? ['packages' => AppWhitelist::query()->where('clear_on_checkout', true)->pluck('package_name')->all(), 'reason' => 'manual']
                            : null;
                        $cmd = $record->commands()->create([
                            'type' => $type, 'payload' => $payload, 'status' => CommandStatus::Pending, 'created_by' => Auth::id(),
                        ]);
                        Audit::log('device.command_queued', $record, ['type' => $type->value, 'command_id' => $cmd->id]);
                        Notification::make()->title('Perintah diantrikan')->body('TV akan menjalankannya saat heartbeat berikutnya (≤1 menit).')->success()->send();
                    }),
                Action::make('history')
                    ->label('Riwayat perintah')
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->iconButton()->tooltip('Riwayat perintah')->color('gray')
                    ->modalHeading(fn (Device $r) => 'Riwayat perintah — kamar '.($r->room?->number ?? '?'))
                    ->modalContent(fn (Device $r) => view('filament.devices.command-history', [
                        'commands' => $r->commands()->latest('id')->limit(15)->get(),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup'),
                Action::make('revoke')
                    ->label('Cabut akses')
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->iconButton()->tooltip('Cabut akses')
                    ->color('danger')
                    ->visible(fn (Device $r) => $r->status === DeviceStatus::Active && static::canEdit($r))
                    ->requiresConfirmation()
                    ->modalDescription('Token TV langsung tidak berlaku. TV harus didaftarkan ulang dengan kode baru.')
                    ->action(function (Device $record) {
                        $record->revoke();
                        Audit::log('device.revoked', $record);
                        Notification::make()->title('Akses TV dicabut')->success()->send();
                    }),
                EditAction::make()->label('Pindah kamar')->icon(Heroicon::OutlinedArrowsRightLeft)->tooltip('Pindah kamar'),
                DeleteAction::make()->before(fn (Device $record) => $record->tokens()->delete()),
            ]);
    }

    public static function updateWarning(): string
    {
        return 'TV yang menyala mengunduh versi baru dalam ±1 menit; TV yang mati menjalankannya saat menyala lagi. TV tanpa Device Owner bisa menampilkan dialog pasang: tekan Instal dengan remote.';
    }

    /** Line under the version: what is going on with the update of this TV. */
    protected static function updateNote(Device $device): ?string
    {
        $release = $device->availableRelease();

        return match (true) {
            ! $release => 'Terbaru',
            $device->hold_updates => "{$release->version_name} tersedia · ditahan",
            $device->updateQueued() => "Mengirim update {$release->version_name}…",
            default => "{$release->version_name} tersedia",
        };
    }

    /** @param iterable<Device> $devices */
    public static function sendUpdates(iterable $devices): void
    {
        $sent = 0;
        $skipped = 0;
        foreach ($devices as $device) {
            $device->queueUpdate(Auth::id()) ? $sent++ : $skipped++;
        }
        Audit::log('device.update_queued', null, ['devices' => $sent, 'skipped' => $skipped]);

        $note = $skipped ? "{$skipped} TV dilewati (sudah terbaru, ditahan, atau perintahnya masih dikirim)." : 'TV menjalankannya dalam ±1 menit.';
        Notification::make()->title($sent ? "Perintah update dikirim ke {$sent} TV" : 'Tidak ada TV yang perlu diupdate')->body($note)
            ->color($sent ? 'success' : 'warning')->icon($sent ? Heroicon::OutlinedCheckCircle : Heroicon::OutlinedInformationCircle)->send();
    }

    public static function getNavigationBadge(): ?string
    {
        $offline = Device::query()->where('status', DeviceStatus::Active)
            ->where(fn ($q) => $q->whereNull('last_seen_at')->orWhere('last_seen_at', '<=', Device::onlineThreshold())->orWhere('screen_on', false))
            ->count();

        return $offline > 0 ? (string) $offline : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'TV mati / standby';
    }

    public static function getPages(): array
    {
        return ['index' => ManageDevices::route('/')];
    }
}

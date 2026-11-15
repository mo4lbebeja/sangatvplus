<?php

namespace App\Filament\Resources\PmsIntegrations;

use App\Enums\UserRole;
use App\Filament\Concerns\RestrictedByRole;
use App\Filament\Resources\PmsIntegrations\Pages\ManagePmsIntegrations;
use App\Models\PmsIntegration;
use App\Support\Audit;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Http\Request;
use UnitEnum;

class PmsIntegrationResource extends Resource
{
    use RestrictedByRole;

    protected static ?string $model = PmsIntegration::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|UnitEnum|null $navigationGroup = 'Integrasi PMS';

    protected static ?string $modelLabel = 'kunci API PMS';

    protected static ?string $pluralModelLabel = 'Kunci API';

    protected static ?string $slug = 'pms-kunci-api';

    protected static ?int $navigationSort = 2;

    protected static function viewRoles(): array
    {
        return [UserRole::It];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nama sistem')->placeholder('VHP PMS')->required()->maxLength(255)->prefixIcon(Heroicon::OutlinedServer),
            Radio::make('auth_mode')->label('Metode keamanan')->required()->default('hmac')
                ->options(['hmac' => 'HMAC-SHA256 (disarankan)', 'bearer' => 'Bearer token (hanya bila PMS tidak bisa HMAC)'])
                ->descriptions(['hmac' => 'Setiap kiriman ditandatangani; tidak bisa dipalsukan atau diulang.', 'bearer' => 'Wajib isi pembatasan IP di bawah.']),
            TagsInput::make('allowed_ips')->label('Batasi IP pengirim')->placeholder('mis. 103.10.20.30 lalu Enter')
                ->helperText('Kosong = semua IP. Isi dengan IP server PMS.')
                ->nestedRecursiveRules(['ip'])
                ->requiredIf('auth_mode', 'bearer'),
            Toggle::make('auto_create_rooms')->label('Buat kamar otomatis bila belum ada')
                ->helperText('Mati = check-in ke kamar yang belum terdaftar ditolak (lebih aman).'),
            Toggle::make('is_active')->label('Aktif')->default(true),
        ]);
    }

    /** Show the plain key & secret exactly once. */
    public static function notifySecret(PmsIntegration $p, string $secret, string $title): void
    {
        Notification::make()->title($title)
            ->body("Key ID: {$p->key_id}\nSecret: {$secret}\n\nSimpan & kirim ke vendor lewat jalur aman. Secret tidak akan ditampilkan lagi.")
            ->success()->persistent()->send();
    }

    /** Sends a correctly signed GET /ping through the real middleware. */
    public static function testConnection(PmsIntegration $p): array
    {
        $ts = (string) time();
        $path = '/api/pms/v1/ping';
        $server = ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_SANGATV_KEY' => $p->key_id, 'REMOTE_ADDR' => '127.0.0.1'];
        $server += $p->auth_mode === 'bearer'
            ? ['HTTP_AUTHORIZATION' => 'Bearer '.$p->secret]
            : ['HTTP_X_SANGATV_TIMESTAMP' => $ts, 'HTTP_X_SANGATV_SIGNATURE' => $p->signatureFor($ts, 'GET', $path, '')];
        $res = app()->handle(Request::create($path, 'GET', server: $server));

        return [$res->getStatusCode(), json_decode((string) $res->getContent(), true)];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Sistem')->weight('semibold')->icon(Heroicon::OutlinedServer),
                TextColumn::make('key_id')->label('Key ID')->fontFamily('mono')->copyable()->size('xs'),
                TextColumn::make('auth_mode')->label('Keamanan')->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'hmac' ? 'HMAC' : 'Bearer')
                    ->color(fn (string $state) => $state === 'hmac' ? 'success' : 'warning')
                    ->icon(fn (string $state) => $state === 'hmac' ? Heroicon::OutlinedShieldCheck : Heroicon::OutlinedLockOpen),
                TextColumn::make('allowed_ips')->label('IP')->badge()->color('gray')->placeholder('Semua'),
                TextColumn::make('last_used_at')->label('Terakhir dipakai')->since()->placeholder('Belum pernah')->icon(Heroicon::OutlinedClock)->color('gray'),
                ToggleColumn::make('is_active')->label('Aktif'),
            ])
            ->recordActions([
                Action::make('test')->label('Uji koneksi')->icon(Heroicon::OutlinedSignal)->iconButton()->tooltip('Uji koneksi (ping bertanda tangan)')->color('primary')
                    ->action(function (PmsIntegration $record) {
                        [$status, $body] = static::testConnection($record);
                        $ok = $status === 200 && ($body['ok'] ?? false);
                        Notification::make()->title($ok ? 'Koneksi OK' : "Gagal (HTTP {$status})")
                            ->body($ok ? 'Server merespons untuk hotel '.($body['data']['hotel'] ?? '').'.' : ($body['error']['message'] ?? ''))
                            ->{$ok ? 'success' : 'danger'}()->send();
                    }),
                Action::make('rotate')->label('Buat ulang secret')->icon(Heroicon::OutlinedArrowPath)->iconButton()->tooltip('Buat ulang secret')->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('Secret lama langsung tidak berlaku. PMS harus memakai secret baru.')
                    ->action(function (PmsIntegration $record) {
                        $secret = PmsIntegration::newSecret();
                        $record->update(['secret' => $secret]);
                        Audit::log('pms.secret_rotated', $record);
                        static::notifySecret($record, $secret, 'Secret baru dibuat');
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManagePmsIntegrations::route('/')];
    }
}

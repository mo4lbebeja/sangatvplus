<?php

namespace App\Vendor\Resources\LoginLogs;

use App\Models\Central\LoginLog;
use App\Vendor\Concerns\VendorAccess;
use App\Vendor\Resources\LoginLogs\Pages\ListLoginLogs;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class LoginLogResource extends Resource
{
    use VendorAccess;

    protected static ?string $slug = 'log-login';

    protected static ?string $model = LoginLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFingerPrint;

    protected static string|UnitEnum|null $navigationGroup = 'Sistem';

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'login';

    protected static ?string $pluralModelLabel = 'Log Login';

    protected static function area(): string
    {
        return 'owner';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('hotel'))
            ->emptyStateHeading('Belum ada login tercatat')->emptyStateDescription('Login Console dan CMS semua hotel akan muncul di sini.')
            ->columns([
                TextColumn::make('created_at')->label('Waktu')->dateTime('d M Y H:i', 'Asia/Jakarta')->sortable()->description(fn (LoginLog $l) => $l->created_at->diffForHumans()),
                IconColumn::make('success')->label('Berhasil')->boolean(),
                TextColumn::make('email')->label('Akun')->searchable()->weight('semibold')
                    ->description(fn (LoginLog $l) => $l->guard === 'vendor' ? 'Console Sijalucom' : 'CMS '.($l->hotel?->name ?? '')),
                TextColumn::make('ip')->label('IP')->icon(Heroicon::OutlinedGlobeAlt)->searchable()->badge()->color(fn (LoginLog $l) => $l->new_ip ? 'warning' : 'gray')
                    ->tooltip(fn (LoginLog $l) => $l->new_ip ? 'IP baru untuk akun ini' : null),
                TextColumn::make('user_agent')->label('Perangkat')->limit(50)->color('gray'),
            ])
            ->filters([
                SelectFilter::make('guard')->label('Panel')->options(['vendor' => 'Console', 'web' => 'CMS hotel']),
                TernaryFilter::make('success')->label('Berhasil'),
                SelectFilter::make('hotel_id')->label('Hotel')->relationship('hotel', 'name'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ListLoginLogs::route('/')];
    }
}

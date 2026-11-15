<?php

namespace App\Vendor\Resources\Hotels;

use App\Models\Central\Hotel;
use App\Vendor\HotelStats;
use App\Vendor\Resources\Hotels\Pages\ManageHotels;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

class HotelResource extends Resource
{
    protected static ?string $model = Hotel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $modelLabel = 'hotel';

    protected static ?string $pluralModelLabel = 'Hotel Penyewa';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nama hotel')->required()->maxLength(120),
            DatePicker::make('service_expires_at')->label('Masa sewa sampai')->native(false)->displayFormat('d M Y')
                ->helperText('Kosong = tanpa batas. 14 hari sebelumnya TV & CMS menampilkan pengingat; setelah lewat, TV masuk Mode lepas.'),
            TextInput::make('contact_name')->label('Kontak hotel')->maxLength(120),
            TextInput::make('contact_email')->label('Email kontak')->email()->maxLength(160),
            Textarea::make('notes')->label('Catatan internal Sijalucom')->rows(3)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('slug')->label('Kode')->weight('bold')->copyable()->searchable(),
                TextColumn::make('name')->label('Nama')->searchable()->description(fn (Hotel $h) => $h->contact_name),
                TextColumn::make('status')->label('CMS')->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'active' ? 'Aktif' : 'Dinonaktifkan')
                    ->color(fn (string $state) => $state === 'active' ? 'success' : 'danger'),
                TextColumn::make('service')->label('Layanan TV')->badge()
                    ->state(fn (Hotel $h) => $h->serviceStatus())
                    ->formatStateUsing(fn (string $state) => ['active' => 'Berjalan', 'expiring' => 'Segera habis', 'released' => 'Mode lepas'][$state])
                    ->color(fn (string $state) => ['active' => 'success', 'expiring' => 'warning', 'released' => 'danger'][$state]),
                TextColumn::make('service_expires_at')->label('Sewa sampai')->date('d M Y')->placeholder('tanpa batas')->sortable(),
                TextColumn::make('tvs')->label('TV online')
                    ->state(fn (Hotel $h) => ($s = HotelStats::for($h))['ok'] ? "{$s['online']} / {$s['tvs']}" : 'galat')
                    ->description(fn (Hotel $h) => ($s = HotelStats::for($h))['ok'] ? "{$s['rooms']} kamar" : str($s['error'] ?? '')->limit(40))
                    ->color(fn (Hotel $h) => HotelStats::for($h)['ok'] ? null : 'danger'),
                TextColumn::make('versions')->label('Versi aplikasi')
                    ->state(fn (Hotel $h) => collect(HotelStats::for($h)['versions'])->map(fn ($n, $v) => "{$v} ({$n})")->implode(', ') ?: '–'),
                TextColumn::make('backup')->label('Backup terakhir')
                    ->state(fn (Hotel $h) => ($t = HotelStats::for($h)['backup']) ? Carbon::createFromTimestamp($t)->diffForHumans() : 'belum ada')
                    ->color(fn (Hotel $h) => ($t = HotelStats::for($h)['backup']) && $t > now()->subHours(36)->getTimestamp() ? 'success' : 'danger'),
            ])
            ->filters([
                SelectFilter::make('status')->label('CMS')->options(['active' => 'Aktif', 'suspended' => 'Dinonaktifkan']),
            ])
            ->defaultSort('id')
            ->recordActions([
                EditAction::make(),
                ActionGroup::make([
                    Action::make('extend')->label('Perpanjang sewa')->icon(Heroicon::OutlinedCalendarDays)
                        ->schema([
                            DatePicker::make('until')->label('Sewa sampai')->native(false)->displayFormat('d M Y')->required()
                                ->default(fn (Hotel $record) => ($record->service_expires_at?->isFuture() ? $record->service_expires_at : now())->addYear()),
                        ])
                        ->action(function (Hotel $record, array $data) {
                            $record->update(['service_expires_at' => Carbon::parse($data['until'])->endOfDay(), 'service_released_at' => null]);
                            Notification::make()->title("Sewa {$record->name} sampai ".$record->service_expires_at->translatedFormat('d M Y'))->success()->send();
                        }),
                    Action::make('release')->label('Lepas layanan TV (kontrak selesai)')->icon(Heroicon::OutlinedPower)->color('danger')
                        ->visible(fn (Hotel $record) => $record->service_released_at === null)
                        ->requiresConfirmation()
                        ->modalDescription('Semua TV hotel ini masuk Mode lepas (kembali ke launcher bawaan) pada heartbeat berikutnya. CMS tetap bisa dibuka untuk ekspor data.')
                        ->action(fn (Hotel $record) => $record->update(['service_released_at' => now()])),
                    Action::make('restore')->label('Pulihkan layanan TV')->icon(Heroicon::OutlinedArrowPath)->color('success')
                        ->visible(fn (Hotel $record) => $record->service_released_at !== null)
                        ->requiresConfirmation()
                        ->action(fn (Hotel $record) => $record->update(['service_released_at' => null])),
                    Action::make('suspend')->label('Nonaktifkan CMS & API')->icon(Heroicon::OutlinedNoSymbol)->color('danger')
                        ->visible(fn (Hotel $record) => $record->status === 'active')
                        ->requiresConfirmation()
                        ->modalDescription('Staf hotel tidak bisa login, TV & PMS hotel ini ditolak server (TV memakai data tersimpan, lalu Mode lepas setelah 30 hari offline). Data tidak dihapus.')
                        ->action(fn (Hotel $record) => $record->update(['status' => 'suspended'])),
                    Action::make('activate')->label('Aktifkan kembali')->icon(Heroicon::OutlinedCheckCircle)->color('success')
                        ->visible(fn (Hotel $record) => $record->status !== 'active')
                        ->action(fn (Hotel $record) => $record->update(['status' => 'active'])),
                    Action::make('refresh')->label('Muat ulang angka')->icon(Heroicon::OutlinedArrowPath)->color('gray')
                        ->action(fn (Hotel $record) => HotelStats::forget($record)),
                ])->tooltip('Aksi lain'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageHotels::route('/')];
    }
}

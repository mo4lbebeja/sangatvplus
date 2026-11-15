<?php

namespace App\Vendor\Resources\Invoices;

use App\Models\Central\Invoice;
use App\Vendor\Billing;
use App\Vendor\Concerns\VendorAccess;
use App\Vendor\Resources\Invoices\Pages\ManageInvoices;
use App\Vendor\Resources\Invoices\Pages\ViewInvoice;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class InvoiceResource extends Resource
{
    use VendorAccess;

    protected static ?string $slug = 'tagihan';

    protected static ?string $model = Invoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCurrencyDollar;

    protected static string|UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'tagihan';

    protected static ?string $pluralModelLabel = 'Tagihan';

    protected static ?string $recordTitleAttribute = 'number';

    protected static function area(): string
    {
        return 'finance';
    }

    public static function getNavigationBadge(): ?string
    {
        $n = Invoice::query()->overdue()->count();

        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Lewat jatuh tempo';
    }

    public static function statusBadge(): TextColumn
    {
        return TextColumn::make('display_status')->label('Status')->badge()
            ->state(fn (Invoice $i) => $i->displayStatus())
            ->formatStateUsing(fn (string $state) => $state === 'overdue' ? 'Lewat tempo' : Invoice::STATUSES[$state])
            ->color(fn (string $state) => ['paid' => 'success', 'overdue' => 'danger', 'unpaid' => 'warning', 'draft' => 'gray', 'void' => 'gray'][$state]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('hotel'))
            ->columns([
                TextColumn::make('number')->label('No. tagihan')->weight('bold')->searchable()->copyable()
                    ->description(fn (Invoice $i) => 'Terbit '.$i->issued_at->translatedFormat('d M Y')),
                TextColumn::make('hotel.name')->label('Hotel')->searchable()->sortable(),
                TextColumn::make('period')->label('Periode')
                    ->state(fn (Invoice $i) => $i->period_start->translatedFormat('d M Y').' – '.$i->period_end->translatedFormat('d M Y'))
                    ->description(fn (Invoice $i) => "{$i->months} bulan"),
                TextColumn::make('total')->label('Jumlah')->formatStateUsing(fn ($state) => Billing::rupiah($state))->sortable()->alignEnd()->weight('semibold'),
                TextColumn::make('due_at')->label('Jatuh tempo')->date('d M Y')->sortable()
                    ->color(fn (Invoice $i) => $i->isOverdue() ? 'danger' : null),
                static::statusBadge(),
                TextColumn::make('paid_at')->label('Dibayar')->dateTime('d M Y', Billing::TZ)->placeholder('–')->sortable()
                    ->description(fn (Invoice $i) => $i->payment_method ? (Invoice::METHODS[$i->payment_method] ?? $i->payment_method) : null),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(Invoice::STATUSES),
                Filter::make('overdue')->label('Lewat jatuh tempo')->query(fn (Builder $query) => $query->overdue()),
                SelectFilter::make('hotel_id')->label('Hotel')->relationship('hotel', 'name')->searchable()->preload(),
            ])
            ->defaultSort('issued_at', 'desc')
            ->recordUrl(fn (Invoice $i) => static::getUrl('view', ['record' => $i]))
            ->recordActions([
                ViewAction::make()->url(fn (Invoice $i) => static::getUrl('view', ['record' => $i])),
                ActionGroup::make(InvoiceActions::all())->tooltip('Aksi'),
            ])
            ->emptyStateHeading('Belum ada tagihan')
            ->emptyStateDescription('Tagihan dibuat saat aktivasi/perpanjang sewa, otomatis sebelum sewa habis, atau lewat tombol "Buat tagihan".');
    }

    public static function getPages(): array
    {
        return ['index' => ManageInvoices::route('/'), 'view' => ViewInvoice::route('/{record}')];
    }
}

<?php

namespace App\Vendor\Resources\Hotels\RelationManagers;

use App\Vendor\Resources\Hotels\HotelResource;
use App\Vendor\Resources\Invoices\InvoiceResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class InvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';

    protected static ?string $title = 'Tagihan';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return HotelResource::allowed('finance');
    }

    public function table(Table $table): Table
    {
        return InvoiceResource::table($table)->defaultSort('issued_at', 'desc');
    }
}

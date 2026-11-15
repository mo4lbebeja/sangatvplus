<?php

namespace App\Vendor\Resources\Hotels\RelationManagers;

use App\Vendor\Resources\Tickets\TicketResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TicketsRelationManager extends RelationManager
{
    protected static string $relationship = 'tickets';

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedLifebuoy;

    protected static ?string $title = 'Tiket';

    public function table(Table $table): Table
    {
        return TicketResource::table($table)->filters([]);
    }
}

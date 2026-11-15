<?php

namespace App\Vendor\Resources\Tickets\Pages;

use App\Models\Central\Ticket;
use App\Models\Central\VendorUser;
use App\Vendor\Resources\Hotels\HotelResource;
use App\Vendor\Resources\Tickets\TicketResource;
use App\Vendor\Support;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\ViewEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class ViewTicket extends ViewRecord
{
    protected static string $resource = TicketResource::class;

    public function getTitle(): string|Htmlable
    {
        return $this->record->subject;
    }

    public function getSubheading(): ?string
    {
        return "{$this->record->number} · {$this->record->hotel->name} · dibuka ".$this->record->created_at->diffForHumans();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reply')->label('Balas')->icon(Heroicon::OutlinedChatBubbleLeftRight)->modalIcon(Heroicon::OutlinedChatBubbleLeftRight)->modalWidth('2xl')
                ->schema([
                    Textarea::make('body')->label('Pesan')->required()->rows(6)->autosize(),
                    FileUpload::make('attachments')->label('Lampiran')->multiple()->maxFiles(5)->maxSize(5120)->disk('local')->directory('tickets')->visibility('private'),
                    Select::make('status')->label('Status setelah dibalas')->options(Ticket::STATUSES)->default('waiting')->required(),
                    Toggle::make('internal')->label('Catatan internal (tidak terlihat hotel, tanpa email)'),
                ])
                ->action(function (array $data) {
                    $user = Filament::auth()->user();
                    if ($user instanceof VendorUser) {
                        Support::replyFromVendor($this->record, $user, $data['body'], $data['status'], (bool) ($data['internal'] ?? false), $data['attachments'] ?? []);
                        Notification::make()->title('Balasan terkirim')->success()->send();
                    }
                }),
            Action::make('assign')->label('Tugaskan')->icon(Heroicon::OutlinedUserPlus)->color('gray')
                ->schema([Select::make('assigned_to')->label('Ditangani oleh')->options(fn () => VendorUser::query()->where('is_active', true)->pluck('name', 'id'))
                    ->default(fn () => $this->record->assigned_to)])
                ->action(fn (array $data) => $this->record->update(['assigned_to' => $data['assigned_to']])),
            Action::make('resolve')->label('Tandai selesai')->icon(Heroicon::OutlinedCheckCircle)->color('success')
                ->visible(fn () => $this->record->isOpen())
                ->action(fn () => $this->record->update(['status' => 'resolved', 'resolved_at' => now()])),
            Action::make('hotel')->label('Buka hotel')->icon(Heroicon::OutlinedBuildingOffice2)->color('gray')
                ->url(fn () => HotelResource::getUrl('view', ['record' => $this->record->hotel_id])),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            ViewEntry::make('thread')->hiddenLabel()->columnSpanFull()->view('vendor-console.ticket-thread'),
        ]);
    }
}

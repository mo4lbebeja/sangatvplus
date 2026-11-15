<?php

namespace App\Filament\Pages;

use App\Models\Central\Ticket;
use App\Models\User;
use App\Tenancy\HotelManager;
use App\Vendor\Support;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/** Hotel staff → Sijalucom: open support tickets and follow the conversation (multi-hotel installs only). */
class SupportPage extends Page implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions, InteractsWithSchemas, InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLifebuoy;

    protected static string|UnitEnum|null $navigationGroup = 'Sistem';

    protected static ?string $navigationLabel = 'Bantuan Sijalucom';

    protected static ?string $title = 'Bantuan Sijalucom';

    protected static ?string $slug = 'bantuan';

    protected static ?int $navigationSort = 90;

    protected string $view = 'filament.pages.support';

    protected ?string $subheading = 'Laporkan kendala TV, tagihan, atau minta bantuan konten. Tim Sijalucom membalas di sini dan lewat email.';

    public static function canAccess(): bool
    {
        return app(HotelManager::class)->current() !== null && Auth::user() instanceof User;
    }

    public static function getNavigationBadge(): ?string
    {
        $hotel = app(HotelManager::class)->current();
        $n = $hotel ? Ticket::query()->where('hotel_id', $hotel->id)->where('status', 'waiting')->count() : 0;

        return $n ? (string) $n : null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')->label('Buat tiket')->icon(Heroicon::OutlinedPlus)->modalIcon(Heroicon::OutlinedLifebuoy)->modalWidth('2xl')
                ->schema([
                    TextInput::make('subject')->label('Judul')->required()->maxLength(160)->placeholder('mis. TV kamar 204 tidak bisa membuka YouTube'),
                    Select::make('category')->label('Kategori')->options(Ticket::CATEGORIES)->default('technical')->required(),
                    Select::make('priority')->label('Prioritas')->options(Ticket::PRIORITIES)->default('normal')->required(),
                    Textarea::make('body')->label('Ceritakan kendalanya')->required()->rows(6)->autosize(),
                    FileUpload::make('attachments')->label('Foto / lampiran')->multiple()->maxFiles(5)->maxSize(5120)->disk('local')->directory('tickets')->visibility('private'),
                ])
                ->action(function (array $data) {
                    $user = Auth::user();
                    $t = Support::open(app(HotelManager::class)->current(), $data['subject'], $data['body'], $data['category'], $data['priority'], $user->name, $user->email, $data['attachments'] ?? []);
                    Notification::make()->title("Tiket {$t->number} terkirim")->body('Tim Sijalucom akan segera membalas.')->success()->send();
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Ticket::query()->where('hotel_id', app(HotelManager::class)->current()?->id ?? 0))
            ->columns([
                TextColumn::make('subject')->label('Tiket')->weight('bold')->wrap()->description(fn (Ticket $t) => $t->number),
                TextColumn::make('category')->label('Kategori')->formatStateUsing(fn ($state) => Ticket::CATEGORIES[$state] ?? $state)->badge()->color('gray'),
                TextColumn::make('status')->label('Status')->badge()->formatStateUsing(fn ($state) => $state === 'waiting' ? 'Dibalas Sijalucom' : (Ticket::STATUSES[$state] ?? $state))
                    ->color(fn ($state) => ['open' => 'info', 'in_progress' => 'info', 'waiting' => 'warning', 'resolved' => 'success', 'closed' => 'gray'][$state] ?? 'gray'),
                TextColumn::make('last_reply_at')->label('Terakhir')->since(),
            ])
            ->defaultSort('last_reply_at', 'desc')
            ->recordActions([
                Action::make('open')->label('Buka')->icon(Heroicon::OutlinedChatBubbleLeftRight)->modalWidth('3xl')
                    ->modalHeading(fn (Ticket $record) => "{$record->number} · {$record->subject}")
                    ->modalContent(fn (Ticket $record) => view('filament.pages.support-thread', ['ticket' => $record->load('messages', 'hotel')]))
                    ->schema([Textarea::make('body')->label('Balas')->rows(3)->autosize()->required(fn (Ticket $record) => $record->status !== 'closed')])
                    ->modalSubmitActionLabel('Kirim balasan')
                    ->action(function (Ticket $record, array $data) {
                        Support::replyFromHotel($record, $data['body'], Auth::user()->name);
                        Notification::make()->title('Balasan terkirim')->success()->send();
                    }),
            ])
            ->emptyStateIcon(Heroicon::OutlinedLifebuoy)
            ->emptyStateHeading('Belum ada tiket')
            ->emptyStateDescription('Tekan "Buat tiket" bila butuh bantuan Sijalucom.');
    }
}

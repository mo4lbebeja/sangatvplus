<?php

namespace App\Vendor\Resources\Hotels\RelationManagers;

use App\Models\Central\HotelDocument;
use App\Models\Central\VendorActivity;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedFolderOpen;

    protected static ?string $title = 'Dokumen';

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('title')->label('Judul')->prefixIcon(Heroicon::OutlinedDocumentText)->required()->maxLength(160)->columnSpanFull(),
            Select::make('type')->label('Jenis')->options(HotelDocument::TYPES)->default('contract')->required(),
            DatePicker::make('signed_at')->label('Tanggal tanda tangan')->native(false)->displayFormat('d M Y'),
            DatePicker::make('expires_at')->label('Berlaku sampai')->native(false)->displayFormat('d M Y'),
            FileUpload::make('path')->label('Berkas (PDF/gambar)')->required()->disk('local')->directory('hotel-documents')->visibility('private')
                ->acceptedFileTypes(['application/pdf', 'image/*'])->maxSize(20480)->columnSpanFull()->disabledOn('edit'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('Dokumen')->weight('bold')->icon(Heroicon::OutlinedDocumentText)->iconColor('primary')
                    ->description(fn (HotelDocument $d) => HotelDocument::TYPES[$d->type] ?? $d->type),
                TextColumn::make('signed_at')->label('Ditandatangani')->date('d M Y')->placeholder('–'),
                TextColumn::make('expires_at')->label('Berlaku sampai')->date('d M Y')->placeholder('–')
                    ->color(fn (HotelDocument $d) => $d->expires_at?->isPast() ? 'danger' : null),
                TextColumn::make('size')->label('Ukuran')->formatStateUsing(fn ($state) => $state ? number_format($state / 1048576, 1, ',', '.').' MB' : '–'),
                TextColumn::make('uploader.name')->label('Diunggah')->placeholder('–')->description(fn (HotelDocument $d) => $d->created_at->translatedFormat('d M Y')),
            ])
            ->headerActions([
                CreateAction::make()->label('Unggah dokumen')->icon(Heroicon::OutlinedArrowUpTray)
                    ->mutateDataUsing(fn (array $data) => $data + ['uploaded_by' => Filament::auth()->id(), 'size' => (int) @Storage::disk('local')->size($data['path'])])
                    ->after(fn (HotelDocument $record) => VendorActivity::record('document.uploaded', "Dokumen diunggah: {$record->title}", $this->getOwnerRecord())),
            ])
            ->recordActions([
                Action::make('download')->label('Unduh')->icon(Heroicon::OutlinedArrowDownTray)->iconButton()
                    ->url(fn (HotelDocument $d) => route('vendor.document.download', $d), shouldOpenInNewTab: true),
                DeleteAction::make(),
            ])
            ->emptyStateIcon(Heroicon::OutlinedFolderOpen)
            ->emptyStateHeading('Belum ada dokumen')
            ->emptyStateDescription('Simpan kontrak sewa, MoU, dan berita acara serah terima di sini.');
    }
}

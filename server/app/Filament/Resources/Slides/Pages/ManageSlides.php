<?php

namespace App\Filament\Resources\Slides\Pages;

use App\Filament\Resources\Slides\SlideResource;
use App\Models\HotelProfile;
use App\Support\Audit;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;

class ManageSlides extends ManageRecords
{
    protected static string $resource = SlideResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->musicAction(),
            CreateAction::make()->icon(Heroicon::OutlinedPlus),
        ];
    }

    /** Background music played on the TV Home screen (loops, stops when leaving Home). */
    private function musicAction(): Action
    {
        $hotel = fn () => HotelProfile::current();

        return Action::make('homeMusic')
            ->label('Musik latar')
            ->icon(Heroicon::OutlinedMusicalNote)
            ->color('gray')
            ->badge(fn () => $hotel()->home_music_enabled && $hotel()->home_music_path ? 'ON' : null)
            ->badgeColor('success')
            ->visible(fn () => SlideResource::canCreate())
            ->modalHeading('Musik latar Beranda')
            ->modalDescription('Diputar berulang di layar Beranda TV, berhenti otomatis saat tamu membuka TV, aplikasi, atau menu lain.')
            ->modalIcon(Heroicon::OutlinedMusicalNote)
            ->modalSubmitActionLabel('Simpan')
            ->fillForm(fn () => [
                'home_music_path' => $hotel()->home_music_path,
                'home_music_volume' => $hotel()->home_music_volume,
                'home_music_enabled' => $hotel()->home_music_enabled,
            ])
            ->schema([
                FileUpload::make('home_music_path')->label('File musik')
                    ->disk('public')->directory('music')
                    ->acceptedFileTypes(['audio/mpeg', 'audio/mp3', 'audio/mp4', 'audio/x-m4a', 'audio/aac', 'audio/ogg'])
                    ->maxSize(8 * 1024)
                    ->helperText('MP3/M4A/AAC/OGG hingga 8 MB. Gunakan musik yang lisensinya boleh diputar di hotel.'),
                TextInput::make('home_music_volume')->label('Volume (%)')->numeric()->minValue(0)->maxValue(100)->required()
                    ->prefixIcon(Heroicon::OutlinedSpeakerWave)->suffix('%'),
                Toggle::make('home_music_enabled')->label('Putar musik di Beranda'),
            ])
            ->action(function (array $data) use ($hotel) {
                $h = $hotel();
                $old = $h->home_music_path;
                $h->fill([
                    'home_music_path' => $data['home_music_path'] ?: null,
                    'home_music_volume' => (int) $data['home_music_volume'],
                    'home_music_enabled' => (bool) $data['home_music_enabled'] && filled($data['home_music_path']),
                ])->save();
                if ($old && $old !== $h->home_music_path) {
                    Storage::disk('public')->delete($old);
                }
                Audit::log('home_music.updated', $h, ['enabled' => $h->home_music_enabled]);
                Notification::make()->title('Musik latar disimpan')->body('TV menerima perubahan dalam ±1 menit.')->success()->send();
            });
    }
}

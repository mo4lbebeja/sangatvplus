<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\Channel;
use App\Models\ChannelCategory;
use App\Models\ChannelView;
use App\Models\Device;
use App\Models\Facility;
use App\Models\GalleryPhoto;
use App\Models\Guest;
use App\Models\HotelProfile;
use App\Models\Room;
use App\Models\Slide;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use ZipArchive;

/**
 * Everything the hotel owns, as CSV files in one ZIP (for the end of a contract or a migration).
 * Secrets (provider credentials, PINs, Wi-Fi password, API keys) are never exported.
 */
class HotelDataExport
{
    public function build(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'sangatv-export').'.zip';
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Tidak bisa membuat file ZIP');
        }

        $hotel = HotelProfile::current();
        $zip->addFromString('hotel.csv', $this->csv(['nama', 'tagline', 'alamat', 'telepon', 'ext', 'ssid_wifi', 'check_in', 'check_out', 'zona_waktu', 'layanan_sampai'], [[
            $hotel->name, $hotel->tagline, $hotel->address, $hotel->reception_phone, $hotel->reception_extension, $hotel->wifi_ssid,
            $hotel->check_in_time, $hotel->check_out_time, $hotel->timezone, $hotel->serviceExpiry()?->toDateString(),
        ]]));

        $zip->addFromString('kamar.csv', $this->csv(['nomor', 'lantai', 'tipe', 'aktif'],
            Room::query()->orderBy('number')->get()->map(fn (Room $r) => [$r->number, $r->floor, $r->type, $r->is_active ? 'ya' : 'tidak'])));

        $zip->addFromString('tamu.csv', $this->csv(['kamar', 'sapaan', 'nama', 'bahasa', 'status', 'check_in', 'check_out', 'reservasi_pms'],
            Guest::query()->with('room')->orderBy('id')->get()->map(fn (Guest $g) => [
                $g->room?->number, $g->salutation, $g->name, $g->language, $g->status->value,
                $g->checked_in_at?->toDateTimeString(), $g->checked_out_at?->toDateTimeString(), $g->external_ref,
            ])));

        $cats = ChannelCategory::query()->pluck('name', 'id');
        $zip->addFromString('channel.csv', $this->csv(['nomor', 'nama', 'kategori', 'jenis_sumber', 'url', 'url_cadangan', 'id_epg', 'bahasa', 'paket_tipe_kamar', 'dewasa', 'aktif'],
            Channel::query()->orderBy('number')->get()->map(fn (Channel $c) => [
                $c->number, $c->name, $cats[$c->channel_category_id] ?? null, $c->source_type->value, $c->url, $c->backup_url,
                $c->epg_id, $c->language, implode('|', (array) $c->room_types), $c->is_adult ? 'ya' : 'tidak', $c->is_active ? 'ya' : 'tidak',
            ])));

        $zip->addFromString('fasilitas.csv', $this->csv(['kategori', 'nama', 'deskripsi', 'lokasi', 'jam'],
            Facility::query()->orderBy('sort_order')->get()->map(fn (Facility $f) => [$f->category, $f->name, $f->description, $f->location, $f->hours])));

        $zip->addFromString('slide.csv', $this->csv(['kicker', 'judul', 'deskripsi', 'gambar'],
            Slide::query()->orderBy('sort_order')->get()->map(fn (Slide $s) => [$s->kicker, $s->title, $s->description, $s->image_path])));

        $zip->addFromString('galeri.csv', $this->csv(['album', 'judul', 'keterangan', 'gambar'],
            GalleryPhoto::query()->get()->map(fn (GalleryPhoto $g) => [$g->album, $g->title, $g->caption, $g->image_path])));

        $zip->addFromString('tv_terdaftar.csv', $this->csv(['kamar', 'model', 'android', 'versi_aplikasi', 'terakhir_online'],
            Device::query()->with('room')->get()->map(fn (Device $d) => [$d->room?->number, $d->model, $d->android_version, $d->app_version, $d->last_seen_at?->toDateTimeString()])));

        $zip->addFromString('pengumuman.csv', $this->csv(['judul', 'pesan', 'tampilan', 'tingkat', 'mulai', 'berakhir'],
            Announcement::query()->orderBy('id')->get()->map(fn (Announcement $a) => [$a->title, $a->message, $a->style, $a->level, $a->starts_at?->toDateTimeString(), $a->ends_at?->toDateTimeString()])));

        // Viewing statistics aggregated per day / channel / room (no individual sessions).
        $zip->addFromString('statistik_tontonan_harian.csv', $this->csv(['tanggal', 'channel', 'kamar', 'menit', 'sesi'],
            ChannelView::query()
                ->leftJoin('channels', 'channels.id', '=', 'channel_views.channel_id')
                ->leftJoin('rooms', 'rooms.id', '=', 'channel_views.room_id')
                ->selectRaw('DATE(channel_views.started_at) as d, channels.name as ch, rooms.number as rm, SUM(channel_views.seconds) as secs, COUNT(*) as n')
                ->groupBy(DB::raw('DATE(channel_views.started_at)'), 'channels.name', 'rooms.number')
                ->orderBy('d')->get()
                ->map(fn ($r) => [$r->d, $r->ch, $r->rm, round($r->secs / 60), $r->n])));

        $zip->addFromString('BACA-SAYA.txt', "Ekspor data SangaTV Plus · {$hotel->name}\nDibuat: ".now()->setTimezone($hotel->timezone ?: 'Asia/Jakarta')->format('d M Y H:i')
            ."\n\nFile CSV (UTF-8, pemisah koma) bisa dibuka di Excel / Google Sheets.\nRahasia (kata sandi Wi-Fi, PIN, kunci API, kredensial penyedia) sengaja tidak diekspor.\n");
        $zip->close();

        return $path;
    }

    /** @param iterable<array<int, mixed>> $rows */
    private function csv(array $header, iterable $rows): string
    {
        $h = fopen('php://temp', 'r+');
        fwrite($h, "\xEF\xBB\xBF"); // BOM so Excel reads UTF-8
        fputcsv($h, $header, escape: '');
        foreach ($rows as $row) {
            fputcsv($h, array_map(fn ($v) => is_bool($v) ? ($v ? 'ya' : 'tidak') : $v, (array) $row), escape: '');
        }
        rewind($h);

        return stream_get_contents($h);
    }
}

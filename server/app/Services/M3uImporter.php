<?php

namespace App\Services;

use App\Enums\SourceType;
use App\Models\Channel;
use App\Models\ChannelCategory;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Imports channels from an M3U / M3U8 playlist (the format IPTV providers and
 * encoders hand out). Supports #EXTINF attributes tvg-chno, tvg-logo, group-title, tvg-id (→ EPG id).
 */
class M3uImporter
{
    public const MAX_PLAYLIST_BYTES = 5 * 1024 * 1024;

    private const MAX_LOGO_BYTES = 1024 * 1024;

    /**
     * @return list<array{name: string, url: string, number: ?int, logo: ?string, group: ?string}>
     */
    public function parse(string $content): array
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content); // BOM
        $lines = preg_split('/\r\n|\r|\n/', $content);
        $entries = [];
        $pending = null;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (str_starts_with($line, '#EXTINF')) {
                $pending = $this->parseExtinf($line);

                continue;
            }
            if (str_starts_with($line, '#EXTGRP:') && $pending !== null) {
                $pending['group'] ??= trim(substr($line, 8)) ?: null;

                continue;
            }
            if (str_starts_with($line, '#')) {
                continue; // #EXTM3U, #EXTVLCOPT, comments …
            }
            if ($pending !== null && self::isPlayableUrl($line)) {
                $entries[] = [...$pending, 'url' => $line];
            }
            $pending = null;
        }

        return $entries;
    }

    /** @return array{name: string, number: ?int, logo: ?string, group: ?string} */
    private function parseExtinf(string $line): array
    {
        preg_match_all('/([a-zA-Z0-9-]+)="([^"]*)"/', $line, $m, PREG_SET_ORDER);
        $attrs = [];
        foreach ($m as [, $k, $v]) {
            $attrs[strtolower($k)] = trim($v);
        }
        // Display name is after the last comma that is not inside quotes.
        $name = trim((string) preg_replace('/^.*?,(?=(?:[^"]*"[^"]*")*[^"]*$)/', '', $line));
        $name = $name !== '' && ! str_starts_with($name, '#EXTINF') ? $name : ($attrs['tvg-name'] ?? 'Tanpa nama');

        $chno = $attrs['tvg-chno'] ?? $attrs['channel-number'] ?? null;
        $group = isset($attrs['group-title']) ? trim(explode(';', $attrs['group-title'])[0]) : null;
        $logo = $attrs['tvg-logo'] ?? null;

        return [
            'name' => Str::limit($name, 120, ''),
            'number' => ctype_digit((string) $chno) && (int) $chno >= 1 && (int) $chno <= 9999 ? (int) $chno : null,
            'logo' => $logo && preg_match('#^https?://#i', $logo) ? $logo : null,
            'group' => $group ?: null,
            'epg_id' => filled($attrs['tvg-id'] ?? null) ? Str::limit($attrs['tvg-id'], 191, '') : null,
        ];
    }

    public static function isPlayableUrl(string $url): bool
    {
        return (bool) preg_match('#^(https?|udp|rtp)://[^\s]+$#i', $url) && strlen($url) <= 2048;
    }

    public static function detectSourceType(string $url, string $origin): SourceType
    {
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));

        return match (true) {
            str_starts_with(strtolower($url), 'udp://'), str_starts_with(strtolower($url), 'rtp://') => SourceType::LocalMulticast,
            str_ends_with($path, '.mpd') => SourceType::ProviderDash,
            $origin === 'provider' => SourceType::ProviderHls,
            default => SourceType::LocalHls,
        };
    }

    /** Fetch a playlist from a URL (IT-only feature; http/https, size and time limited). */
    public function fetch(string $url): string
    {
        if (! preg_match('#^https?://#i', $url)) {
            throw new \InvalidArgumentException('URL playlist harus http:// atau https://');
        }
        $res = Http::timeout(15)->withHeaders(['User-Agent' => 'SangaTVPlus-CMS'])->get($url);
        if (! $res->successful()) {
            throw new \RuntimeException('Playlist tidak dapat diunduh (HTTP '.$res->status().').');
        }
        $body = $res->body();
        if (strlen($body) > self::MAX_PLAYLIST_BYTES) {
            throw new \RuntimeException('Playlist terlalu besar (maks 5 MB).');
        }

        return $body;
    }

    /**
     * @param  list<array{name: string, url: string, number: ?int, logo: ?string, group: ?string}>  $entries
     * @param  array{origin: string, provider_id?: ?int, start_number?: ?int, activate?: bool, download_logos?: bool}  $opt
     * @return array{created: int, skipped: int, logos: int}
     */
    public function import(array $entries, array $opt): array
    {
        $existingUrls = Channel::query()->pluck('url')->flip();
        $usedNumbers = Channel::query()->pluck('number')->flip();
        $next = max((int) ($opt['start_number'] ?? 0), (int) Channel::query()->max('number') + 1, 1);
        $sort = (int) Channel::query()->max('sort_order');
        $created = $skipped = $logos = 0;

        DB::transaction(function () use ($entries, $opt, &$existingUrls, &$usedNumbers, &$next, &$sort, &$created, &$skipped, &$logos) {
            foreach ($entries as $e) {
                if (isset($existingUrls[$e['url']])) {
                    $skipped++;

                    continue;
                }

                $number = $e['number'] !== null && ! isset($usedNumbers[$e['number']]) ? $e['number'] : null;
                if ($number === null) {
                    while (isset($usedNumbers[$next])) {
                        $next++;
                    }
                    $number = $next++;
                }
                if ($number > 9999) {
                    $skipped++;

                    continue;
                }

                $type = self::detectSourceType($e['url'], $opt['origin']);
                $logoPath = ($opt['download_logos'] ?? false) && $e['logo'] ? $this->downloadLogo($e['logo']) : null;
                $logos += $logoPath ? 1 : 0;

                Channel::create([
                    'number' => $number,
                    'name' => $e['name'],
                    'channel_category_id' => $e['group'] ? $this->category($e['group'])->id : null,
                    'channel_provider_id' => $type->isProvider() ? ($opt['provider_id'] ?? null) : null,
                    'source_type' => $type,
                    'url' => $e['url'],
                    'logo_path' => $logoPath,
                    'epg_id' => $e['epg_id'] ?? null,
                    'sort_order' => $sort += 10,
                    'is_active' => (bool) ($opt['activate'] ?? false),
                ]);

                $existingUrls[$e['url']] = true;
                $usedNumbers[$number] = true;
                $created++;
            }
        });

        Audit::log('channel.imported', null, ['created' => $created, 'skipped' => $skipped, 'origin' => $opt['origin']]);

        return compact('created', 'skipped', 'logos');
    }

    private function category(string $name): ChannelCategory
    {
        $slug = Str::slug($name) ?: 'lainnya';

        return ChannelCategory::query()->firstOrCreate(
            ['slug' => $slug],
            ['name' => Str::limit($name, 64, ''), 'sort_order' => ((int) ChannelCategory::query()->max('sort_order')) + 10],
        );
    }

    /** Stores the logo locally so TVs never need internet access for it. */
    private function downloadLogo(string $url): ?string
    {
        try {
            $res = Http::timeout(5)->get($url);
            $body = $res->body();
            if (! $res->successful() || strlen($body) === 0 || strlen($body) > self::MAX_LOGO_BYTES) {
                return null;
            }
            $info = @getimagesizefromstring($body);
            $ext = match ($info['mime'] ?? null) {
                'image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/gif' => 'gif',
                default => null,
            };
            if ($ext === null) {
                return null;
            }
            $path = 'channel-logos/'.Str::uuid().'.'.$ext;
            Storage::disk('public')->put($path, $body);

            return $path;
        } catch (Throwable) {
            return null;
        }
    }
}

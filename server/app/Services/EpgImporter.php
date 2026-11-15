<?php

namespace App\Services;

use App\Models\Channel;
use App\Models\EpgProgramme;
use App\Models\HotelProfile;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use SimpleXMLElement;
use Throwable;
use XMLReader;

/**
 * Imports XMLTV guides (plain or .gz) for channels whose `epg_id` is set.
 * Streams the file with XMLReader, so multi-MB guides stay cheap on memory.
 * Sources are tried in the order given; the first source that has a channel wins.
 */
class EpgImporter
{
    public const CATALOG_KEY = 'epg:catalog';

    /** Programmes kept: from 2 h ago up to 4 days ahead. */
    private const PAST_HOURS = 2;

    private const FUTURE_DAYS = 4;

    /** @return array{channels: int, programmes: int, sources: array<int, string>} */
    public function sync(?HotelProfile $hotel = null): array
    {
        $hotel ??= HotelProfile::current();
        $urls = $hotel->epgUrlList();
        if ($urls === []) {
            throw new RuntimeException('Belum ada sumber EPG (Profil Hotel → Channel TV).');
        }

        $wanted = Channel::query()->whereNotNull('epg_id')->where('epg_id', '!=', '')->get(['id', 'epg_id'])
            ->groupBy(fn (Channel $c) => mb_strtolower($c->epg_id))
            ->map(fn ($g) => $g->pluck('id')->all())
            ->all();

        $tz = $hotel->timezone ?: 'Asia/Jakarta';
        $from = CarbonImmutable::now()->subHours(self::PAST_HOURS);
        $to = CarbonImmutable::now()->addDays(self::FUTURE_DAYS);

        $catalog = [];
        $rowsByEpgId = [];
        $notes = [];

        foreach ($urls as $url) {
            try {
                $file = $this->download($url);
                try {
                    [$cat, $rows] = $this->parse($file, $wanted, $from, $to, $tz, array_keys($rowsByEpgId));
                } finally {
                    @unlink($file);
                }
                $catalog += $cat;
                foreach ($rows as $epgId => $list) {
                    $rowsByEpgId[$epgId] = $list; // earlier sources already excluded in parse()
                }
                $notes[] = parse_url($url, PHP_URL_HOST).': '.count($cat).' channel';
            } catch (Throwable $e) {
                $notes[] = parse_url($url, PHP_URL_HOST).': gagal ('.Str::limit($e->getMessage(), 80).')';
            }
        }

        if ($catalog !== []) {
            // Kept in source order: auto-match prefers the first (highest-priority) source.
            Cache::forever(self::CATALOG_KEY, $catalog);
        }

        $count = 0;
        DB::transaction(function () use ($rowsByEpgId, $wanted, &$count) {
            foreach ($rowsByEpgId as $epgId => $rows) {
                $channelIds = $wanted[$epgId] ?? [];
                if ($channelIds === [] || $rows === []) {
                    continue;
                }
                EpgProgramme::query()->whereIn('channel_id', $channelIds)->delete();
                foreach ($channelIds as $channelId) {
                    foreach (array_chunk($rows, 300) as $chunk) {
                        EpgProgramme::query()->insert(array_map(fn ($r) => $r + ['channel_id' => $channelId], $chunk));
                        $count += count($chunk);
                    }
                }
            }
            EpgProgramme::query()->where('ends_at', '<', now()->subDay())->delete();
        });

        $matched = count(array_filter(array_keys($wanted), fn ($id) => ! empty($rowsByEpgId[$id])));
        $hotel->forceFill([
            'epg_synced_at' => now(),
            'epg_status' => Str::limit("{$matched}/".count($wanted)." channel terisi, {$count} acara · ".implode(' · ', $notes), 250),
        ])->saveQuietly();

        return ['channels' => $matched, 'programmes' => $count, 'sources' => $notes];
    }

    /** @return array<string, string> XMLTV channel id => display name (from the last sync) */
    public static function catalog(): array
    {
        return Cache::get(self::CATALOG_KEY, []);
    }

    /**
     * Fill empty `epg_id`s by exact name match against the catalog ("Metro TV HD" ↔ "Metro TV").
     *
     * @return int channels matched
     */
    public static function autoMatch(): int
    {
        $byName = [];
        foreach (self::catalog() as $id => $name) {
            $byName[self::normalize($name)] ??= $id;
            $byName[self::normalize(preg_replace('/\.[a-z]{2}$/i', '', (string) $id))] ??= $id;
        }

        $n = 0;
        Channel::query()->where(fn ($query) => $query->whereNull('epg_id')->orWhere('epg_id', ''))->each(function (Channel $c) use ($byName, &$n) {
            $id = $byName[self::normalize($c->name)] ?? null;
            if ($id) {
                $c->update(['epg_id' => $id]);
                $n++;
            }
        });

        return $n;
    }

    public static function normalize(string $name): string
    {
        $s = mb_strtolower($name);
        $s = preg_replace('/\b(hd|fhd|uhd|4k|sd|nasional|national|official|channel)\b/u', '', $s);

        return preg_replace('/[^a-z0-9]+/u', '', $s);
    }

    private function download(string $url): string
    {
        $file = tempnam(sys_get_temp_dir(), 'epg');
        $res = Http::timeout(120)->connectTimeout(15)->withHeaders(['User-Agent' => 'SangaTVPlus-EPG'])->sink($file)->get($url);
        if (! $res->successful()) {
            @unlink($file);
            throw new RuntimeException('HTTP '.$res->status());
        }

        return $file;
    }

    /**
     * @param  array<string, array<int>>  $wanted  lower-cased epg id => channel ids
     * @param  array<int, string>  $skip  epg ids already filled by an earlier source
     * @return array{0: array<string, string>, 1: array<string, array<int, array<string, mixed>>>}
     */
    private function parse(string $file, array $wanted, CarbonImmutable $from, CarbonImmutable $to, string $tz, array $skip): array
    {
        $fh = fopen($file, 'rb');
        $magic = fread($fh, 2);
        fclose($fh);
        $path = $magic === "\x1f\x8b" ? 'compress.zlib://'.$file : $file;

        $reader = new XMLReader;
        if (! $reader->open($path, null, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE)) {
            throw new RuntimeException('Bukan file XMLTV');
        }

        $catalog = [];
        $rows = [];
        $skip = array_flip($skip);

        while ($reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT) {
                continue;
            }
            if ($reader->name === 'channel') {
                $id = (string) $reader->getAttribute('id');
                $node = $this->node($reader);
                if ($id !== '' && $node) {
                    $catalog[$id] = $this->text($node, 'display-name') ?: $id;
                }

                continue;
            }
            if ($reader->name !== 'programme') {
                continue;
            }
            $key = mb_strtolower((string) $reader->getAttribute('channel'));
            if (! isset($wanted[$key]) || isset($skip[$key])) {
                continue;
            }
            $start = $this->time((string) $reader->getAttribute('start'), $tz);
            $stop = $this->time((string) $reader->getAttribute('stop'), $tz);
            if (! $start || ! $stop || $stop->lte($start) || $stop->lt($from) || $start->gt($to)) {
                continue;
            }
            $node = $this->node($reader);
            $title = $node ? $this->text($node, 'title') : '';
            if ($title === '') {
                continue;
            }
            $desc = $this->text($node, 'desc');
            $rows[$key][] = [
                'starts_at' => $start->utc()->format('Y-m-d H:i:s'),
                'ends_at' => $stop->utc()->format('Y-m-d H:i:s'),
                'title' => Str::limit($title, 250, ''),
                'description' => $desc !== '' ? Str::limit(str_replace('_x000D_', '', $desc), 1000) : null,
                'category' => Str::limit($this->text($node, 'category'), 115, '') ?: null,
            ];
        }
        $reader->close();

        foreach ($rows as $k => $list) {
            usort($list, fn ($a, $b) => strcmp($a['starts_at'], $b['starts_at']));
            $rows[$k] = $list;
        }

        return [$catalog, $rows];
    }

    private function node(XMLReader $reader): ?SimpleXMLElement
    {
        $xml = $reader->readOuterXml();

        return $xml !== '' ? (simplexml_load_string($xml, options: LIBXML_NONET) ?: null) : null;
    }

    /** Prefer Indonesian, then English, then whatever comes first. */
    private function text(SimpleXMLElement $node, string $tag): string
    {
        $best = null;
        foreach ($node->{$tag} as $el) {
            $lang = strtolower((string) $el['lang']);
            $val = trim((string) $el);
            if ($val === '') {
                continue;
            }
            if ($lang === 'id') {
                return $val;
            }
            if ($best === null || $lang === 'en') {
                $best = $val;
            }
        }

        return (string) $best;
    }

    /** XMLTV time: "20261115060000 +0700" (offset optional → hotel time zone). */
    private function time(string $v, string $tz): ?CarbonImmutable
    {
        if (! preg_match('/^(\d{14})\s*([+-]\d{4})?/', trim($v), $m)) {
            return null;
        }

        return isset($m[2])
            ? CarbonImmutable::createFromFormat('YmdHis O', $m[1].' '.$m[2])
            : CarbonImmutable::createFromFormat('YmdHis', $m[1], $tz);
    }
}

<?php

namespace App\Services;

use App\Enums\SourceType;
use App\Models\Channel;
use App\Models\ChannelCheck;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Probes channel streams from the server: the HLS/DASH manifest must answer 200
 * with a valid playlist/manifest. UDP/RTP multicast lives on the IPTV VLAN and
 * cannot be probed from here ("skipped"); TVs report those through playback reports.
 */
class ChannelHealthChecker
{
    public function __construct(private StreamUrlSigner $signer) {}

    /** @return array{status: string, message: ?string} */
    public function check(Channel $channel): array
    {
        $signed = $this->signer->sign($channel);
        $url = $signed['url'];

        if (preg_match('#^(udp|rtp)://#i', $url)) {
            return $this->save($channel, 'skipped', 'Multicast lokal: dipantau lewat laporan TV');
        }

        $result = $this->probe($url, $channel->source_type);
        if ($result['status'] !== 'ok' && $signed['backup_url']) {
            $backup = $this->probe($signed['backup_url'], $channel->source_type);
            if ($backup['status'] === 'ok') {
                return $this->save($channel, 'ok', 'Utama gagal ('.$result['message'].'), cadangan normal');
            }
        }

        return $this->save($channel, $result['status'], $result['message']);
    }

    /** @return array{status: string, message: ?string} */
    private function probe(string $url, SourceType $type): array
    {
        try {
            $res = Http::timeout(8)->connectTimeout(5)->withHeaders(['User-Agent' => 'SangaTVPlus-HealthCheck'])->get($url);
            if (! $res->successful()) {
                return ['status' => 'error', 'message' => 'HTTP '.$res->status()];
            }
            $body = ltrim(substr($res->body(), 0, 4096), "\xEF\xBB\xBF \r\n\t");
            $valid = $type === SourceType::ProviderDash ? str_contains($body, '<MPD') : str_starts_with($body, '#EXTM3U');

            return $valid ? ['status' => 'ok', 'message' => null] : ['status' => 'error', 'message' => 'Bukan playlist/manifest yang valid'];
        } catch (Throwable $e) {
            return ['status' => 'error', 'message' => str_contains($e->getMessage(), 'timed out') ? 'Waktu habis (tidak merespons)' : 'Tidak dapat dihubungi'];
        }
    }

    private function save(Channel $channel, string $status, ?string $message): array
    {
        $channel->forceFill(['health_status' => $status, 'health_message' => $message, 'health_checked_at' => now()])->saveQuietly();
        ChannelCheck::query()->create(['channel_id' => $channel->id, 'status' => $status, 'message' => $message ? mb_substr($message, 0, 255) : null, 'checked_at' => now()]);

        return ['status' => $status, 'message' => $message];
    }

    /** @return array{ok: int, error: int, skipped: int} */
    public function checkAll(): array
    {
        $n = ['ok' => 0, 'error' => 0, 'skipped' => 0];
        Channel::query()->with('provider')->where('is_active', true)->each(function (Channel $c) use (&$n) {
            $status = $this->check($c)['status'];
            $n[$status] = ($n[$status] ?? 0) + 1;
        });

        return $n;
    }
}

<?php

namespace App\Services;

use App\Enums\ProviderSigning;
use App\Models\Channel;
use App\Models\ChannelProvider;
use Carbon\CarbonImmutable;

/**
 * Produces the URLs a TV is allowed to play.
 *
 * Local sources are returned unchanged (only reachable inside the IPTV VLAN).
 * Provider sources are signed with a short-lived token using the provider's
 * credentials, which never leave the server.
 */
class StreamUrlSigner
{
    /** @return array{url: string, backup_url: ?string, expires_at: ?CarbonImmutable} */
    public function sign(Channel $channel, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $provider = $channel->provider;

        if (! $channel->source_type->isProvider() || ! $provider || $provider->signing === ProviderSigning::None) {
            return ['url' => $channel->url, 'backup_url' => $channel->backup_url, 'expires_at' => null];
        }

        $expires = $now->addSeconds(max(60, $provider->token_ttl_seconds));

        return [
            'url' => $this->signUrl($channel->url, $provider, $expires),
            'backup_url' => $channel->backup_url ? $this->signUrl($channel->backup_url, $provider, $expires) : null,
            'expires_at' => $expires,
        ];
    }

    private function signUrl(string $url, ChannelProvider $provider, CarbonImmutable $expires): string
    {
        return match ($provider->signing) {
            ProviderSigning::HmacQuery => $this->hmacQuery($url, $provider, $expires),
            default => $url,
        };
    }

    /**
     * Generic scheme: token = base64url(HMAC-SHA256(secret, path + expires)).
     * Adjust per provider once their real API/spec is known.
     */
    private function hmacQuery(string $url, ChannelProvider $provider, CarbonImmutable $expires): string
    {
        $secret = (string) ($provider->credentials['secret'] ?? '');
        $path = (string) (parse_url($url, PHP_URL_PATH) ?? '/');
        $ts = $expires->getTimestamp();
        $token = rtrim(strtr(base64_encode(hash_hmac('sha256', $path.$ts, $secret, true)), '+/', '-_'), '=');

        return $url.(str_contains($url, '?') ? '&' : '?').http_build_query(['expires' => $ts, 'token' => $token]);
    }
}

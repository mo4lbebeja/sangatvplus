<?php

namespace App\Http\Middleware;

use App\Models\PmsEvent;
use App\Models\PmsIntegration;
use App\Support\Audit;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates PMS requests and logs every call.
 *
 * Headers: X-SangaTV-Key (key id), and either
 *  - hmac:   X-SangaTV-Timestamp (unix s, ±300 s) + X-SangaTV-Signature = hex HMAC-SHA256(secret, "{ts}.{METHOD}.{path}.{body}")
 *  - bearer: Authorization: Bearer <secret>   (only for PMS that cannot sign; combine with an IP whitelist)
 * Idempotency-Key (optional): a repeated key returns the first response without re-applying.
 */
class AuthenticatePms
{
    public const MAX_SKEW = 300;

    public function handle(Request $request, Closure $next): Response
    {
        $integration = PmsIntegration::query()->where('key_id', (string) $request->header('X-SangaTV-Key'))->first();

        if (! $integration || ! $integration->is_active) {
            return $this->deny($request, $integration, 401, 'unauthorized', 'Kunci API tidak dikenal atau nonaktif.');
        }
        if (! $integration->ipAllowed($request->ip())) {
            return $this->deny($request, $integration, 403, 'forbidden_ip', 'IP '.$request->ip().' tidak diizinkan untuk kunci ini.');
        }

        if ($integration->auth_mode === 'bearer') {
            if (! hash_equals((string) $integration->secret, (string) $request->bearerToken())) {
                return $this->deny($request, $integration, 401, 'invalid_token', 'Token tidak valid.');
            }
        } else {
            $ts = (string) $request->header('X-SangaTV-Timestamp');
            if (! ctype_digit($ts) || abs(time() - (int) $ts) > self::MAX_SKEW) {
                return $this->deny($request, $integration, 401, 'stale_timestamp', 'X-SangaTV-Timestamp tidak ada atau selisih waktu > 5 menit (cek jam server PMS).');
            }
            $expected = $integration->signatureFor($ts, $request->method(), $request->getPathInfo(), $request->getContent());
            if (! hash_equals($expected, strtolower((string) $request->header('X-SangaTV-Signature')))) {
                return $this->deny($request, $integration, 401, 'invalid_signature', 'Tanda tangan HMAC tidak cocok.');
            }
        }

        // Idempotency: replay the stored response for a repeated key.
        $idem = substr((string) $request->header('Idempotency-Key'), 0, 100) ?: null;
        if ($idem && $request->method() !== 'GET') {
            $prev = PmsEvent::query()->where('pms_integration_id', $integration->id)->where('idempotency_key', $idem)
                ->where('status', 'ok')->latest('id')->first();
            if ($prev) {
                return response()->json($prev->response, $prev->http_status)->header('Idempotent-Replayed', 'true');
            }
        }

        $request->attributes->set('pms_integration', $integration);
        Audit::$actor = $integration;
        $integration->forceFill(['last_used_at' => now()])->saveQuietly();

        try {
            $response = $next($request);
        } finally {
            Audit::$actor = null;
        }

        $this->log($request, $integration, $response, $idem);

        return $response;
    }

    private function deny(Request $request, ?PmsIntegration $integration, int $status, string $code, string $message): JsonResponse
    {
        $response = response()->json(['ok' => false, 'error' => ['code' => $code, 'message' => $message]], $status);
        $this->log($request, $integration, $response, null);

        return $response;
    }

    private function log(Request $request, ?PmsIntegration $integration, Response $response, ?string $idem): void
    {
        $body = json_decode((string) $response->getContent(), true);
        $payload = $request->json()->all();
        PmsEvent::create([
            'pms_integration_id' => $integration?->id,
            'method' => $request->method(),
            'path' => substr($request->getPathInfo(), 0, 255),
            'event' => $request->attributes->get('pms_event'),
            'reservation_id' => substr((string) ($payload['reservation_id'] ?? $request->route('reservation') ?? ''), 0, 64) ?: null,
            'room_number' => substr((string) ($payload['room'] ?? $request->route('number') ?? ''), 0, 16) ?: null,
            'http_status' => $response->getStatusCode(),
            'status' => $response->isSuccessful() ? 'ok' : 'error',
            'message' => is_array($body) ? substr((string) ($body['error']['message'] ?? $body['message'] ?? ''), 0, 255) ?: null : null,
            'idempotency_key' => $idem,
            'payload' => $payload ? array_slice($payload, 0, 50, true) : null,
            'response' => is_array($body) ? $body : null,
            'ip' => $request->ip(),
        ]);
    }
}

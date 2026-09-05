<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Http\Controllers;

use App\Contexts\GameWorld\GiftCodes\Actions\IngestApprovedGiftCodeObservation;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodeIngestionObservation;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

final class GiftCodeSourceWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        string $source,
        IngestApprovedGiftCodeObservation $ingest,
    ): JsonResponse {
        // This is a machine-to-machine API. Force validation/signature failures through
        // Laravel's JSON exception path even when a webhook sender omits an Accept header.
        $request->headers->set('Accept', 'application/json');

        abort_unless((bool) config('game_world.gift_codes.source_webhook_ingestion', false), 404);
        abort_unless((bool) config('game_world.gift_codes.approved_source_ingestion', false), 404);

        $registry = GiftCodeSourceRegistry::query()->findOrFail($source);
        abort_unless($registry->is_active && $registry->ingestion_enabled && $registry->revoked_at === null, 404);

        $this->authorizeSignature($request, (string) $registry->id);
        $validated = $request->validate([
            'observations' => ['required', 'array', 'min:1', 'max:100'],
            'observations.*.code' => ['required', 'string', 'max:64'],
            'observations.*.assertion' => ['required', 'string', 'in:available,invalid,expires,reward,applicability'],
            'observations.*.assertion_payload' => ['nullable', 'array'],
            'observations.*.source_url' => ['required', 'url:https', 'max:2048'],
            'observations.*.claimed_expires_at' => ['nullable', 'date'],
            'observations.*.expiry_precision' => ['nullable', 'string', 'in:instant,minute,hour,day'],
            'observations.*.expiry_timezone' => ['nullable', 'timezone'],
            'observations.*.published_at' => ['nullable', 'date'],
            'observations.*.source_version' => ['required', 'string', 'max:120'],
            'observations.*.content_fingerprint' => ['required', 'string', 'max:256'],
        ]);

        $accepted = 0;
        $duplicates = 0;
        $quarantined = 0;
        $results = [];
        foreach ($validated['observations'] as $index => $row) {
            $fingerprint = (string) $row['content_fingerprint'];
            $result = $ingest->handle((string) $registry->id, new GiftCodeIngestionObservation(
                code: (string) $row['code'],
                assertion: (string) $row['assertion'],
                assertionPayload: isset($row['assertion_payload']) && is_array($row['assertion_payload'])
                    ? $row['assertion_payload']
                    : null,
                sourceUrl: (string) $row['source_url'],
                claimedExpiresAt: isset($row['claimed_expires_at']) ? (string) $row['claimed_expires_at'] : null,
                expiryPrecision: isset($row['expiry_precision']) ? (string) $row['expiry_precision'] : null,
                expiryTimezone: isset($row['expiry_timezone']) ? (string) $row['expiry_timezone'] : null,
                publishedAt: isset($row['published_at']) ? (string) $row['published_at'] : null,
                sourceVersion: (string) $row['source_version'],
                retrievalVersion: 'signed-webhook-v1',
                parserVersion: 'signed-webhook-v1',
                contentFingerprint: $fingerprint,
                rawEvidenceRef: 'signed-webhook:'.$registry->id.':'.hash('sha256', $fingerprint),
                verificationPassed: true,
            ));
            $accepted += $result['accepted'] ? 1 : 0;
            $duplicates += $result['duplicate'] ? 1 : 0;
            $quarantined += $result['quarantined'] ? 1 : 0;
            $results[] = [
                'index' => $index,
                'giftCodeId' => $result['gift_code_id'],
                'accepted' => $result['accepted'],
                'duplicate' => $result['duplicate'],
                'quarantined' => $result['quarantined'],
            ];
        }

        return response()->json([
            'sourceId' => (string) $registry->id,
            'examined' => count($results),
            'accepted' => $accepted,
            'duplicates' => $duplicates,
            'quarantined' => $quarantined,
            'results' => $results,
        ], 202);
    }

    private function authorizeSignature(Request $request, string $sourceId): void
    {
        $secret = trim((string) config('game_world.gift_codes.source_webhook_secret', ''));
        if (strlen($secret) < 32) {
            abort(503, 'Gift Code source webhook verification is not configured.');
        }
        $timestamp = trim((string) $request->header('X-Kingshot-Timestamp', ''));
        $signature = trim((string) $request->header('X-Kingshot-Signature', ''));
        if (! ctype_digit($timestamp) || preg_match('/^sha256=([a-f0-9]{64})$/D', $signature, $matches) !== 1) {
            throw ValidationException::withMessages(['signature' => 'The source webhook signature is invalid.']);
        }
        $skew = max(60, min(3600, (int) config('game_world.gift_codes.source_webhook_clock_skew_seconds', 300)));
        if (abs(time() - (int) $timestamp) > $skew) {
            throw ValidationException::withMessages(['signature' => 'The source webhook timestamp is outside the accepted window.']);
        }
        $expected = hash_hmac('sha256', $sourceId.'.'.$timestamp.'.'.$request->getContent(), $secret);
        if (! hash_equals($expected, $matches[1])) {
            throw ValidationException::withMessages(['signature' => 'The source webhook signature is invalid.']);
        }

        $replayKey = 'gift-code-source-webhook:'.hash('sha256', $sourceId.'|'.$timestamp.'|'.$matches[1]);
        if (! Cache::add($replayKey, true, $skew * 2)) {
            throw ValidationException::withMessages(['signature' => 'This source webhook delivery was already processed.']);
        }
    }
}

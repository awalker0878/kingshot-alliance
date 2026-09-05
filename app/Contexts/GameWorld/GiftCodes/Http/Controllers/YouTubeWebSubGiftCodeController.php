<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Http\Controllers;

use App\Contexts\GameWorld\GiftCodes\Actions\IngestGiftCodeProviderPublication;
use App\Contexts\GameWorld\GiftCodes\Actions\RecordGiftCodePushDelivery;
use App\Contexts\GameWorld\GiftCodes\Adapters\YouTubeChannelGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceSubscription;
use App\Contexts\GameWorld\GiftCodes\Services\GiftCodePushPayloadLimits;
use App\Contexts\GameWorld\GiftCodes\Services\GiftCodePushDeliveryIdentity;
use App\Contexts\GameWorld\GiftCodes\Services\YouTubeVideoGiftCodeFetcher;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodePushDelivery;
use App\Shared\Infrastructure\Http\Controller;
use DOMDocument;
use DOMNode;
use DOMXPath;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use UnexpectedValueException;

final class YouTubeWebSubGiftCodeController extends Controller
{
    public function verify(Request $request, string $source): Response
    {
        $registry = $this->source($source);
        $policy = $registry->provenance_policy ?? [];
        $channelId = is_string($policy['youtube_channel_id'] ?? null) ? trim($policy['youtube_channel_id']) : '';
        $mode = trim((string) $request->query('hub_mode', $request->query('hub.mode', '')));
        $topic = trim((string) $request->query('hub_topic', $request->query('hub.topic', '')));
        $challenge = (string) $request->query('hub_challenge', $request->query('hub.challenge', ''));
        $leaseSeconds = (int) $request->query('hub_lease_seconds', $request->query('hub.lease_seconds', 0));
        $expectedTopic = 'https://www.youtube.com/feeds/videos.xml?channel_id='.$channelId;

        if (! in_array($mode, ['subscribe', 'unsubscribe'], true)
            || $channelId === ''
            || ! hash_equals($expectedTopic, $topic)
            || $challenge === ''
            || mb_strlen($challenge) > 4096) {
            abort(404);
        }

        GiftCodeSourceSubscription::query()->updateOrCreate(
            [
                'gift_code_source_id' => (string) $registry->id,
                'provider' => 'youtube',
                'transport' => 'websub',
            ],
            [
                'topic_or_rule' => $expectedTopic,
                'configured_identity' => ['channel_id' => $channelId],
                'status' => $mode === 'subscribe' ? 'active' : 'disabled',
                'activated_at' => $mode === 'subscribe' ? now() : null,
                'expires_at' => $mode === 'subscribe' && $leaseSeconds > 0 ? now()->addSeconds(min($leaseSeconds, 31_536_000)) : null,
                'last_verified_at' => now(),
                'last_error_code' => null,
            ],
        );

        return response($challenge, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function receive(
        Request $request,
        string $source,
        GiftCodePushPayloadLimits $limits,
        GiftCodePushDeliveryIdentity $identity,
        RecordGiftCodePushDelivery $record,
        YouTubeVideoGiftCodeFetcher $fetcher,
        IngestGiftCodeProviderPublication $ingest,
    ): JsonResponse {
        $registry = $this->source($source);
        $body = $request->getContent();
        $limits->assertBounded($body);
        $this->authorizeSignature($request, $registry, $body);
        $entries = $this->entries($body, $registry);

        $processed = 0;
        $duplicates = 0;
        $accepted = 0;
        foreach ($entries as $entry) {
            $replayKey = $identity->replayKey('youtube', (string) $registry->id, $entry['event_id'], $entry['video_id'].'|'.$entry['updated_at']);
            $delivery = $record->handle(new GiftCodePushDelivery(
                provider: 'youtube',
                sourceKey: $registry->source_key,
                providerEventId: $entry['event_id'],
                providerItemId: $entry['video_id'],
                replayKey: $replayKey,
                payloadSha256: hash('sha256', $body),
                correlationId: trim((string) $request->header('X-Request-Id')) ?: null,
            ));
            if (! $delivery->wasRecentlyCreated) {
                $duplicates++;
                $registry->increment('replay_rejection_count');
                continue;
            }

            try {
                $publication = $fetcher->fetch($registry, $entry['video_id']);
                $outcome = $ingest->handle($registry, $publication, 'youtube-websub-v1', true);
                $delivery->forceFill([
                    'processing_status' => $outcome->status,
                    'processed_at' => now(),
                ])->save();
                $processed++;
                $accepted += $outcome->accepted;
            } catch (\Throwable $exception) {
                $delivery->forceFill([
                    'processing_status' => 'failed',
                    'error_code' => 'canonical_fetch_or_ingestion_failed',
                    'processed_at' => now(),
                ])->save();
                throw $exception;
            }
        }

        $registry->forceFill([
            'last_push_received_at' => now(),
            'last_provider_event_at' => now(),
            'last_health_checked_at' => now(),
        ])->save();
        GiftCodeSourceSubscription::query()
            ->where('gift_code_source_id', $registry->id)
            ->where('provider', 'youtube')
            ->where('transport', 'websub')
            ->update(['last_event_received_at' => now(), 'last_error_code' => null]);

        return response()->json([
            'sourceId' => (string) $registry->id,
            'events' => count($entries),
            'processed' => $processed,
            'duplicates' => $duplicates,
            'accepted' => $accepted,
        ], 202);
    }

    private function source(string $source): GiftCodeSourceRegistry
    {
        abort_unless((bool) config('game_world.gift_codes.approved_source_ingestion', false), 404);
        $registry = GiftCodeSourceRegistry::query()->findOrFail($source);
        abort_unless(
            $registry->is_active
            && $registry->ingestion_enabled
            && $registry->push_enabled
            && $registry->revoked_at === null
            && $registry->adapter_key === YouTubeChannelGiftCodeSourceAdapter::KEY,
            404,
        );

        return $registry;
    }

    private function authorizeSignature(Request $request, GiftCodeSourceRegistry $source, string $body): void
    {
        $secret = trim((string) config('game_world.gift_codes.youtube_websub_secret', ''));
        if (strlen($secret) < 32) {
            abort(503, 'YouTube WebSub verification is not configured.');
        }
        $header = trim((string) $request->header('X-Hub-Signature', ''));
        if (preg_match('/^(sha1|sha256)=([a-f0-9]+)$/D', $header, $matches) !== 1) {
            $source->increment('signature_failure_count');
            throw ValidationException::withMessages(['signature' => 'The YouTube WebSub signature is missing or invalid.']);
        }
        $algorithm = $matches[1];
        $expected = hash_hmac($algorithm, $body, $secret);
        if (! hash_equals($expected, $matches[2])) {
            $source->increment('signature_failure_count');
            throw ValidationException::withMessages(['signature' => 'The YouTube WebSub signature is invalid.']);
        }
    }

    /** @return list<array{video_id:string,event_id:string,updated_at:string}> */
    private function entries(string $body, GiftCodeSourceRegistry $source): array
    {
        if (stripos($body, '<!DOCTYPE') !== false || stripos($body, '<!ENTITY') !== false) {
            throw new UnexpectedValueException('YouTube WebSub documents may not declare document types or entities.');
        }
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        try {
            $document = new DOMDocument;
            if (! $document->loadXML($body, LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_COMPACT)) {
                throw new UnexpectedValueException('YouTube WebSub delivered malformed XML.');
            }
            $xpath = new DOMXPath($document);
            $nodes = $xpath->query('//*[local-name()="entry"]');
            if ($nodes === false || $nodes->length > 100) {
                throw new UnexpectedValueException('YouTube WebSub delivered an invalid or unbounded entry collection.');
            }
            $expectedChannelId = trim((string) (($source->provenance_policy ?? [])['youtube_channel_id'] ?? ''));
            $result = [];
            foreach ($nodes as $node) {
                if (! $node instanceof DOMNode) continue;
                $videoId = $this->firstText($xpath, $node, './*[local-name()="videoId"]');
                $channelId = $this->firstText($xpath, $node, './*[local-name()="channelId"]');
                $updatedAt = $this->firstText($xpath, $node, './*[local-name()="updated"]') ?? '';
                $eventId = $this->firstText($xpath, $node, './*[local-name()="id"]') ?? 'yt:video:'.$videoId;
                if ($videoId === null || preg_match('/^[A-Za-z0-9_-]{6,32}$/D', $videoId) !== 1 || $channelId === null || ! hash_equals($expectedChannelId, $channelId)) {
                    throw new UnexpectedValueException('YouTube WebSub delivery did not match the configured channel identity.');
                }
                $result[] = [
                    'video_id' => $videoId,
                    'event_id' => mb_substr($eventId, 0, 255),
                    'updated_at' => mb_substr($updatedAt, 0, 120),
                ];
            }
            return $result;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function firstText(DOMXPath $xpath, DOMNode $context, string $expression): ?string
    {
        $nodes = $xpath->query($expression, $context);
        if ($nodes === false || $nodes->length === 0) return null;
        $value = trim((string) $nodes->item(0)?->textContent);
        return $value === '' ? null : $value;
    }
}

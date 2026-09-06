<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Http\Controllers;

use App\Contexts\GameWorld\GiftCodes\Actions\CompleteGiftCodePushDelivery;
use App\Contexts\GameWorld\GiftCodes\Actions\IngestGiftCodeProviderPublication;
use App\Contexts\GameWorld\GiftCodes\Actions\RecordGiftCodePushDelivery;
use App\Contexts\GameWorld\GiftCodes\Actions\RecordGiftCodeSourcePushActivity;
use App\Contexts\GameWorld\GiftCodes\Actions\UpdateGiftCodeSourceSubscription;
use App\Contexts\GameWorld\GiftCodes\Adapters\OfficialXGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;
use App\Contexts\GameWorld\GiftCodes\Services\GiftCodePushDeliveryIdentity;
use App\Contexts\GameWorld\GiftCodes\Services\GiftCodePushPayloadLimits;
use App\Contexts\GameWorld\GiftCodes\Services\XPostGiftCodeFetcher;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodePushDelivery;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use UnexpectedValueException;

final class XGiftCodeWebhookController extends Controller
{
    public function verify(
        Request $request,
        string $source,
        UpdateGiftCodeSourceSubscription $subscriptions,
    ): JsonResponse {
        $registry = $this->source($source);
        $secret = $this->consumerSecret();
        $crcToken = trim((string) $request->query('crc_token', ''));
        if ($crcToken === '' || mb_strlen($crcToken) > 4096) {
            abort(400, 'Missing or invalid CRC token.');
        }

        $digest = base64_encode(hash_hmac('sha256', $crcToken, $secret, true));
        $subscriptions->handle(
            sourceId: (string) $registry->id,
            provider: 'x',
            transport: 'filtered_stream_webhook',
            attributes: ['last_verified_at' => now(), 'last_error_code' => null],
        );

        return response()->json(['response_token' => 'sha256='.$digest]);
    }

    public function receive(
        Request $request,
        string $source,
        GiftCodePushPayloadLimits $limits,
        GiftCodePushDeliveryIdentity $identity,
        RecordGiftCodePushDelivery $record,
        CompleteGiftCodePushDelivery $complete,
        RecordGiftCodeSourcePushActivity $activity,
        UpdateGiftCodeSourceSubscription $subscriptions,
        XPostGiftCodeFetcher $fetcher,
        IngestGiftCodeProviderPublication $ingest,
    ): JsonResponse {
        $registry = $this->source($source);
        $body = $request->getContent();
        $limits->assertBounded($body);
        $this->authorizeSignature($request, $registry, $body, $activity);
        $payload = $request->json()->all();
        $event = $this->event($payload, $registry);

        $replayKey = $identity->replayKey(
            'x',
            (string) $registry->id,
            $event['event_id'],
            $event['post_id'].'|'.$event['edit_key'],
        );
        $delivery = $record->handle(new GiftCodePushDelivery(
            provider: 'x',
            sourceKey: $registry->source_key,
            providerEventId: $event['event_id'],
            providerItemId: $event['post_id'],
            replayKey: $replayKey,
            payloadSha256: hash('sha256', $body),
            correlationId: trim((string) $request->header('X-Request-Id')) ?: null,
        ));

        if (! $delivery->created) {
            $activity->handle((string) $registry->id, 'replay_rejection');

            return response()->json([
                'sourceId' => (string) $registry->id,
                'processed' => 0,
                'duplicates' => 1,
                'accepted' => 0,
            ], 200);
        }

        try {
            $publication = $fetcher->fetch($registry, $event['post_id']);
            $outcome = $ingest->handle((string) $registry->id, $publication, 'x-filtered-stream-webhook-v1', true);
            $complete->handle($delivery->deliveryId, $outcome->status);
            $activity->handle((string) $registry->id, 'received');
            $subscriptions->handle(
                sourceId: (string) $registry->id,
                provider: 'x',
                transport: 'filtered_stream_webhook',
                attributes: ['last_event_received_at' => now(), 'last_error_code' => null],
            );

            return response()->json([
                'sourceId' => (string) $registry->id,
                'processed' => 1,
                'duplicates' => 0,
                'accepted' => $outcome->accepted,
            ], 200);
        } catch (\Throwable $exception) {
            $complete->handle($delivery->deliveryId, 'failed', 'canonical_fetch_or_ingestion_failed');
            throw $exception;
        }
    }

    private function source(string $source): GiftCodeSourceRegistry
    {
        abort_unless((bool) config('game_world.gift_codes.approved_source_ingestion', false), 404);
        abort_unless((bool) config('game_world.gift_codes.x_realtime_transport', false), 404);
        abort_unless((bool) config('game_world.gift_codes.x_filtered_stream_webhook_entitled', false), 404);
        $registry = GiftCodeSourceRegistry::query()->findOrFail($source);
        abort_unless(
            $registry->is_active
            && $registry->ingestion_enabled
            && $registry->push_enabled
            && $registry->revoked_at === null
            && $registry->adapter_key === OfficialXGiftCodeSourceAdapter::KEY,
            404,
        );

        return $registry;
    }

    private function authorizeSignature(
        Request $request,
        GiftCodeSourceRegistry $source,
        string $body,
        RecordGiftCodeSourcePushActivity $activity,
    ): void {
        $signature = trim((string) $request->header('x-twitter-webhooks-signature', ''));
        $expected = 'sha256='.base64_encode(hash_hmac('sha256', $body, $this->consumerSecret(), true));
        if ($signature === '' || ! hash_equals($expected, $signature)) {
            $activity->handle((string) $source->id, 'signature_failure');
            throw ValidationException::withMessages(['signature' => 'The X webhook signature is invalid.']);
        }
    }

    private function consumerSecret(): string
    {
        $secret = trim((string) config('game_world.gift_codes.x_consumer_secret', ''));
        if (strlen($secret) < 20) {
            abort(503, 'X webhook consumer-secret verification is not configured.');
        }

        return $secret;
    }

    /** @param array<string,mixed> $payload
     * @return array{post_id:string,event_id:string,edit_key:string}
     */
    private function event(array $payload, GiftCodeSourceRegistry $source): array
    {
        $post = $payload['data'] ?? null;
        if (! is_array($post)) {
            throw new UnexpectedValueException('X filtered-stream webhook did not include a Post object.');
        }
        $postId = is_string($post['id'] ?? null) ? trim($post['id']) : '';
        $authorId = is_string($post['author_id'] ?? null) ? trim($post['author_id']) : '';
        if (preg_match('/^[0-9]{1,32}$/D', $postId) !== 1
            || preg_match('/^[0-9]{1,32}$/D', $authorId) !== 1) {
            throw new UnexpectedValueException('X filtered-stream webhook returned an invalid Post identity.');
        }
        $expectedAuthor = trim((string) (($source->provenance_policy ?? [])['x_user_id'] ?? ''));
        if ($expectedAuthor === '' || ! hash_equals($expectedAuthor, $authorId)) {
            throw new UnexpectedValueException('X filtered-stream webhook Post was not authored by the configured official account.');
        }

        $history = $post['edit_history_tweet_ids'] ?? [];
        $editIds = [];
        if (is_array($history)) {
            foreach ($history as $id) {
                if (is_string($id) && preg_match('/^[0-9]{1,32}$/D', trim($id)) === 1) {
                    $editIds[] = trim($id);
                }
            }
        }
        $editKey = $editIds === [] ? $postId : implode(',', $editIds);
        $rules = $payload['matching_rules'] ?? [];
        $ruleIds = [];
        if (is_array($rules)) {
            foreach ($rules as $rule) {
                if (is_array($rule) && is_string($rule['id'] ?? null)) {
                    $ruleIds[] = trim($rule['id']);
                }
            }
        }

        return [
            'post_id' => $postId,
            'event_id' => 'x-post:'.$postId.($ruleIds === [] ? '' : ':'.hash('sha256', implode(',', $ruleIds))),
            'edit_key' => $editKey,
        ];
    }
}

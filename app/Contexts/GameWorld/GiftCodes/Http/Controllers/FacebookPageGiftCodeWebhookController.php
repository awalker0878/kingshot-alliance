<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Http\Controllers;

use App\Contexts\GameWorld\GiftCodes\Actions\IngestGiftCodeProviderPublication;
use App\Contexts\GameWorld\GiftCodes\Actions\RecordGiftCodePushDelivery;
use App\Contexts\GameWorld\GiftCodes\Adapters\FacebookPageGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceSubscription;
use App\Contexts\GameWorld\GiftCodes\Services\FacebookPagePostGiftCodeFetcher;
use App\Contexts\GameWorld\GiftCodes\Services\GiftCodePushDeliveryIdentity;
use App\Contexts\GameWorld\GiftCodes\Services\GiftCodePushPayloadLimits;
use App\Contexts\GameWorld\GiftCodes\Services\MetaWebhookAuthenticator;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodePushDelivery;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use UnexpectedValueException;

final class FacebookPageGiftCodeWebhookController extends Controller
{
    public function verify(Request $request, string $source): Response
    {
        $registry = $this->source($source);
        $mode = trim((string) $request->query('hub_mode', $request->query('hub.mode', '')));
        $challenge = (string) $request->query('hub_challenge', $request->query('hub.challenge', ''));
        $verifyToken = trim((string) $request->query('hub_verify_token', $request->query('hub.verify_token', '')));
        $configuredToken = trim((string) config('game_world.gift_codes.meta_webhook_verify_token', ''));

        if ($mode !== 'subscribe'
            || strlen($configuredToken) < 24
            || ! hash_equals($configuredToken, $verifyToken)
            || $challenge === ''
            || mb_strlen($challenge) > 4096) {
            abort(404);
        }

        $policy = $registry->provenance_policy ?? [];
        $pageId = is_string($policy['facebook_page_id'] ?? null) ? trim($policy['facebook_page_id']) : '';
        GiftCodeSourceSubscription::query()->updateOrCreate(
            [
                'gift_code_source_id' => (string) $registry->id,
                'provider' => 'facebook',
                'transport' => 'webhook',
            ],
            [
                'topic_or_rule' => 'page:feed',
                'configured_identity' => ['page_id' => $pageId, 'field' => 'feed'],
                'status' => 'active',
                'activated_at' => now(),
                'last_verified_at' => now(),
                'secret_version' => hash('sha256', $configuredToken),
                'last_error_code' => null,
            ],
        );

        return response($challenge, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function receive(
        Request $request,
        string $source,
        GiftCodePushPayloadLimits $limits,
        MetaWebhookAuthenticator $authenticator,
        GiftCodePushDeliveryIdentity $identity,
        RecordGiftCodePushDelivery $record,
        FacebookPagePostGiftCodeFetcher $fetcher,
        IngestGiftCodeProviderPublication $ingest,
    ): JsonResponse {
        $registry = $this->source($source);
        $body = $request->getContent();
        $limits->assertBounded($body);
        $authenticator->assertValid($request, $registry, $body);

        $events = $this->events($request->json()->all(), $registry);
        $processed = 0;
        $duplicates = 0;
        $accepted = 0;

        foreach ($events as $event) {
            $replayKey = $identity->replayKey(
                'facebook',
                (string) $registry->id,
                $event['event_id'],
                $event['post_id'].'|'.$event['verb'].'|'.$event['time'],
            );
            $delivery = $record->handle(new GiftCodePushDelivery(
                provider: 'facebook',
                sourceKey: $registry->source_key,
                providerEventId: $event['event_id'],
                providerItemId: $event['post_id'],
                replayKey: $replayKey,
                payloadSha256: hash('sha256', $body),
                correlationId: trim((string) $request->header('X-FB-Trace-ID')) ?: null,
            ));
            if (! $delivery->wasRecentlyCreated) {
                $duplicates++;
                $registry->increment('replay_rejection_count');
                continue;
            }

            try {
                if (in_array($event['verb'], ['remove', 'delete'], true)) {
                    $delivery->forceFill([
                        'processing_status' => 'ignored',
                        'processed_at' => now(),
                    ])->save();
                    $processed++;
                    continue;
                }

                $publication = $fetcher->fetch($registry, $event['post_id']);
                $outcome = $ingest->handle($registry, $publication, 'facebook-page-webhook-v1', true);
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
            ->where('provider', 'facebook')
            ->where('transport', 'webhook')
            ->update(['last_event_received_at' => now(), 'last_error_code' => null]);

        return response()->json([
            'sourceId' => (string) $registry->id,
            'events' => count($events),
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
            && $registry->adapter_key === FacebookPageGiftCodeSourceAdapter::KEY,
            404,
        );

        return $registry;
    }

    /** @param array<string,mixed> $payload
     * @return list<array{event_id:string,post_id:string,verb:string,time:string}>
     */
    private function events(array $payload, GiftCodeSourceRegistry $source): array
    {
        if (($payload['object'] ?? null) !== 'page') {
            throw new UnexpectedValueException('The Meta webhook payload must be a Page object delivery.');
        }
        $entries = $payload['entry'] ?? null;
        if (! is_array($entries) || ! array_is_list($entries) || count($entries) > 100) {
            throw new UnexpectedValueException('The Meta Page webhook delivered an invalid or unbounded entry collection.');
        }

        $pageId = trim((string) (($source->provenance_policy ?? [])['facebook_page_id'] ?? ''));
        $events = [];
        foreach ($entries as $entryIndex => $entry) {
            if (! is_array($entry) || ! hash_equals($pageId, trim((string) ($entry['id'] ?? '')))) {
                throw new UnexpectedValueException('The Meta Page webhook delivery did not match the configured Page identity.');
            }
            $entryTime = (string) ($entry['time'] ?? '');
            $changes = $entry['changes'] ?? null;
            if (! is_array($changes) || ! array_is_list($changes) || count($changes) > 100) {
                throw new UnexpectedValueException('The Meta Page webhook entry contained an invalid change collection.');
            }
            foreach ($changes as $changeIndex => $change) {
                if (! is_array($change) || ($change['field'] ?? null) !== 'feed') {
                    continue;
                }
                $value = $change['value'] ?? null;
                if (! is_array($value) || ($value['item'] ?? null) !== 'post') {
                    continue;
                }
                $postId = trim((string) ($value['post_id'] ?? ''));
                if ($postId === '' || mb_strlen($postId) > 180) {
                    throw new UnexpectedValueException('The Meta Page feed change did not include a valid post id.');
                }
                $verb = mb_strtolower(trim((string) ($value['verb'] ?? 'add')));
                if (! in_array($verb, ['add', 'edited', 'remove', 'delete'], true)) {
                    continue;
                }
                $events[] = [
                    'event_id' => sprintf('page:%s:%s:%s:%d:%d', $pageId, $postId, $verb, $entryIndex, $changeIndex),
                    'post_id' => $postId,
                    'verb' => $verb,
                    'time' => mb_substr($entryTime, 0, 32),
                ];
            }
        }

        return $events;
    }
}

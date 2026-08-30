<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Integrations\Contracts;

final class WebhookEventCatalog
{
    /** @var array<string, array{scope: 'alliance'|'global', required: list<string>}> */
    private const PUBLIC_EVENTS = [
        'content.published' => ['scope' => 'alliance', 'required' => ['content_item_id', 'revision_number']],
        'event.created' => ['scope' => 'alliance', 'required' => ['scope', 'target_id']],
        'event.updated' => ['scope' => 'alliance', 'required' => ['scope', 'target_id']],
        'event.cancelled' => ['scope' => 'alliance', 'required' => ['scope', 'target_id']],
        'member.updated' => ['scope' => 'alliance', 'required' => ['member_id', 'player_id', 'change']],
        'member.left' => ['scope' => 'alliance', 'required' => ['member_id', 'player_id', 'source']],
        'recruitment.candidate.stage_changed' => ['scope' => 'alliance', 'required' => ['candidate_id', 'from_stage', 'to_stage']],
        'recruitment.candidate.joined' => ['scope' => 'alliance', 'required' => ['candidate_id', 'membership_invitation_id']],
        'gift_code.created' => ['scope' => 'global', 'required' => ['version', 'gift_code_id', 'code', 'status', 'status_revision']],
        'gift_code.provenance_added' => ['scope' => 'global', 'required' => ['version', 'gift_code_id', 'code', 'source_type', 'status_revision']],
        'gift_code.status_changed' => ['scope' => 'global', 'required' => ['version', 'gift_code_id', 'previous_status', 'status', 'status_revision']],
        'gift_code.expiry_changed' => ['scope' => 'global', 'required' => ['version', 'gift_code_id', 'status_revision', 'expires_revision']],
        'broadcast.schedule.updated' => ['scope' => 'alliance', 'required' => ['schedule_id', 'content_item_id', 'timezone', 'weekdays', 'local_time', 'next_run_at']],
        'broadcast.schedule.cancelled' => ['scope' => 'alliance', 'required' => ['schedule_id', 'content_item_id', 'reason']],
        'broadcast.run.queued' => ['scope' => 'alliance', 'required' => ['broadcast_run_id', 'content_item_id', 'recipient_count', 'delivery_count']],
        'broadcast.delivery.succeeded' => ['scope' => 'alliance', 'required' => ['broadcast_run_id', 'content_item_id', 'channel', 'status', 'attempt_count']],
        'broadcast.delivery.failed' => ['scope' => 'alliance', 'required' => ['broadcast_run_id', 'content_item_id', 'channel', 'status', 'attempt_count', 'retryable']],
    ];

    public static function isPublic(string $eventType): bool
    {
        return array_key_exists($eventType, self::PUBLIC_EVENTS);
    }

    public static function isValidSelector(string $eventType): bool
    {
        return $eventType === '*' || self::isPublic($eventType);
    }

    /** @return list<string> */
    public static function publicEvents(): array
    {
        return array_keys(self::PUBLIC_EVENTS);
    }

    public static function isGlobal(string $eventType): bool
    {
        return (self::PUBLIC_EVENTS[$eventType]['scope'] ?? null) === 'global';
    }

    /** @param array<string, mixed> $payload */
    public static function payloadIsValid(string $eventType, array $payload): bool
    {
        $contract = self::PUBLIC_EVENTS[$eventType] ?? null;
        if ($contract === null) {
            return false;
        }

        foreach ($contract['required'] as $field) {
            if (! array_key_exists($field, $payload)) {
                return false;
            }
        }

        return true;
    }

    /** @return array<string, array{scope: 'alliance'|'global', required: list<string>}> */
    public static function contracts(): array
    {
        return self::PUBLIC_EVENTS;
    }
}

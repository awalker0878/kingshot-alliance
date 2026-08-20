<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Membership\Actions;

use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Http\BulkActionResult;
use App\Shared\Infrastructure\Http\BulkItemResult;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

final readonly class BulkChangeMembershipStatus
{
    public function __construct(
        private PreviewMembershipStatusBulkChange $preview,
        private UpdateMembershipStatus $change,
        private PlayerReferenceQuery $players,
        private AuditRecorder $audit,
    ) {}

    /** @param non-empty-list<string> $membershipIds */
    public function handle(
        string $actorPlayerId,
        string $allianceId,
        array $membershipIds,
        MembershipStatus $target,
    ): BulkActionResult {
        $preview = $this->preview->handle($actorPlayerId, $allianceId, $membershipIds, $target);
        $items = [];

        foreach ($preview['items'] as $item) {
            if ($item['outcome'] === 'skipped') {
                $items[] = BulkItemResult::skipped($item['itemId'], $item['label'], $item['code']);
                continue;
            }
            if ($item['outcome'] !== 'ready') {
                $items[] = BulkItemResult::failed($item['itemId'], $item['label'], $item['code']);
                continue;
            }

            try {
                $this->change->handle($allianceId, $actorPlayerId, $item['itemId'], $target);
                $items[] = BulkItemResult::succeeded($item['itemId'], $item['label'], 'status-updated');
            } catch (ModelNotFoundException) {
                $items[] = BulkItemResult::failed($item['itemId'], $item['label'], 'member-unavailable');
            } catch (AuthorizationException) {
                $items[] = BulkItemResult::failed($item['itemId'], $item['label'], 'member-protected');
            } catch (ValidationException $exception) {
                $code = match (true) {
                    array_key_exists('quota', $exception->errors()) => 'capacity-reached',
                    array_key_exists('membership', $exception->errors()) => 'member-protected',
                    default => 'update-not-allowed',
                };
                $items[] = BulkItemResult::failed($item['itemId'], $item['label'], $code);
            }
        }

        /** @var non-empty-list<BulkItemResult> $items */
        $result = new BulkActionResult('membership-status-change', $items);
        $payload = $result->toArray();
        $this->audit->record(
            'membership.members.bulk_status_changed',
            $this->players->require($actorPlayerId),
            null,
            $allianceId,
            [
                'target_status' => $target->value,
                'membership_ids' => $membershipIds,
                'succeeded' => $payload['succeeded'],
                'failed' => $payload['failed'],
                'skipped' => $payload['skipped'],
            ],
        );

        return $result;
    }
}

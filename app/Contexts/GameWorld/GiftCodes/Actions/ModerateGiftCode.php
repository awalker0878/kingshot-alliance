<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Actions;

use App\Contexts\Accounts\Identity\ValueObjects\AccountIdentity;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeModerationAction;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeStatus;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCode;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeCuratorGrant;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeModerationDecision;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeProvenance;
use App\Contexts\Platform\Administration\Models\PlatformAdministrator;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ModerateGiftCode
{
    public function __construct(
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
        private ReconcileGiftCodeStatus $reconcile,
    ) {}

    /**
     * @param list<string> $evidenceIds
     * @param array<string, mixed> $metadata
     */
    public function handle(
        AccountIdentity $actor,
        string $giftCodeId,
        GiftCodeModerationAction $action,
        ?string $reason = null,
        array $evidenceIds = [],
        ?GiftCodeStatus $proposedStatus = null,
        array $metadata = [],
    ): string {
        $this->authorize($actor);
        abort_unless((bool) config('game_world.gift_codes.moderation', false), 404);

        $reason = $reason === null ? null : trim($reason);
        if ($action->requiresReason() && ($reason === null || $reason === '')) {
            throw ValidationException::withMessages(['reason' => 'A moderation reason is required for this action.']);
        }
        if ($action === GiftCodeModerationAction::ResolveDispute && $proposedStatus === null) {
            throw ValidationException::withMessages(['proposed_status' => 'A resolved status is required.']);
        }
        if ($action === GiftCodeModerationAction::CorrectExpiry && ! array_key_exists('expires_at', $metadata)) {
            throw ValidationException::withMessages(['expires_at' => 'A corrected expiry value is required.']);
        }

        return DB::transaction(function () use (
            $actor,
            $giftCodeId,
            $action,
            $reason,
            $evidenceIds,
            $proposedStatus,
            $metadata,
        ): string {
            $giftCode = GiftCode::query()->whereKey($giftCodeId)->lockForUpdate()->firstOrFail();
            $evidenceIds = array_values(array_unique(array_map('strval', $evidenceIds)));
            if ($evidenceIds !== []) {
                $matched = GiftCodeProvenance::query()
                    ->where('gift_code_id', $giftCodeId)
                    ->whereIn('id', $evidenceIds)
                    ->count();
                if ($matched !== count($evidenceIds)) {
                    throw ValidationException::withMessages([
                        'evidence_ids' => 'Every evidence reference must belong to the Gift Code being reviewed.',
                    ]);
                }
            }

            $decision = GiftCodeModerationDecision::query()->create([
                'gift_code_id' => $giftCodeId,
                'actor_user_id' => $actor->userId,
                'action' => $action,
                'reason' => $reason,
                'previous_status' => $giftCode->status->value,
                'proposed_status' => $proposedStatus?->value,
                'evidence_ids' => $evidenceIds,
                'metadata' => $metadata,
                'decided_at' => now(),
            ]);

            $eventMetadata = [
                'gift_code_id' => $giftCodeId,
                'decision_id' => (string) $decision->id,
                'action' => $action->value,
                'reason' => $reason,
                'previous_status' => $giftCode->status->value,
                'proposed_status' => $proposedStatus?->value,
                'evidence_ids' => $evidenceIds,
                'metadata' => $metadata,
            ];
            $this->audit->record('game_world.gift_code_moderated', $actor, $decision, null, $eventMetadata);
            $this->outbox->record(
                'gift_code.moderated',
                null,
                $decision,
                $eventMetadata,
                'gift-code-moderation:'.$decision->id,
                'gift-code:'.$giftCodeId,
            );

            $this->reconcile->handle($giftCodeId, $actor);

            return (string) $decision->id;
        });
    }

    private function authorize(AccountIdentity $actor): void
    {
        $authorized = $actor->emailVerified
            && $actor->multiFactorConfirmed
            && (
                PlatformAdministrator::activeForUserId($actor->userId)
                || GiftCodeCuratorGrant::activeForUserId($actor->userId)
            );

        if (! $authorized) {
            throw new AuthorizationException('MFA-protected Gift Code curator access is required.');
        }
    }
}

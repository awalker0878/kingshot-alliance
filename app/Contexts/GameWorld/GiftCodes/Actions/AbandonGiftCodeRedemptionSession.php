<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Actions;

use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeRedemptionSessionStatus;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeRedemptionSession;
use App\Shared\Infrastructure\AuditTrail\Contracts\AuditActor;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

final readonly class AbandonGiftCodeRedemptionSession
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(AuditActor $actor, string $sessionId): GiftCodeRedemptionSession
    {
        $userId = $actor->auditUserId();
        if ($userId === null) {
            throw new AuthorizationException('An authenticated account is required for a Gift Code session.');
        }
        $session = GiftCodeRedemptionSession::query()
            ->whereKey($sessionId)
            ->where('user_id', $userId)
            ->lockForUpdate()
            ->firstOrFail();
        if ($session->status === GiftCodeRedemptionSessionStatus::Completed) {
            throw ValidationException::withMessages(['session' => 'A completed Gift Code session cannot be abandoned.']);
        }
        if ($session->status !== GiftCodeRedemptionSessionStatus::Abandoned) {
            $session->status = GiftCodeRedemptionSessionStatus::Abandoned;
            $session->abandoned_at = CarbonImmutable::now('UTC');
            $session->last_activity_at = CarbonImmutable::now('UTC');
            $session->save();
            $this->audit->record(
                'game_world.gift_code_redemption_session.abandoned',
                $actor,
                'gift_code_redemption_session',
                (string) $session->id,
            );
        }

        return $session->refresh();
    }
}

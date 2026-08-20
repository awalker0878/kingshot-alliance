<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Integrations\Actions;

use App\Contexts\Alliance\Access\Services\AllianceWriteAuthorization;
use App\Contexts\Platform\Integrations\Enums\ExternalActorProvider;
use App\Contexts\Platform\Integrations\Models\ExternalActorPairingCode;
use App\Contexts\Platform\Integrations\Services\ExternalActorIdentity;
use App\Contexts\Platform\Integrations\ValueObjects\IssuedExternalActorPairingCode;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;

final readonly class IssueExternalActorPairingCode
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(
        private AllianceWriteAuthorization $allianceAuthority,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        string $allianceId,
        string $actorPlayerId,
        ExternalActorProvider $provider,
    ): IssuedExternalActorPairingCode {
        return DB::transaction(function () use ($allianceId, $actorPlayerId, $provider): IssuedExternalActorPairingCode {
            [$alliance, $actor] = $this->allianceAuthority->authorizeMemberExclusive($actorPlayerId, $allianceId);
            $now = now();
            ExternalActorPairingCode::query()
                ->where('alliance_id', $alliance->allianceId)
                ->where('player_id', $actor->playerId)
                ->where('provider', $provider->value)
                ->whereNull('consumed_at')
                ->whereNull('cancelled_at')
                ->lockForUpdate()
                ->update(['cancelled_at' => $now, 'updated_at' => $now]);

            $rawCode = $this->randomCode();
            $expiresAt = $now->copy()->addMinutes(10);
            $pairing = ExternalActorPairingCode::query()->create([
                'alliance_id' => $alliance->allianceId,
                'player_id' => $actor->playerId,
                'provider' => $provider,
                'code_hash' => ExternalActorIdentity::pairingCodeHash($rawCode),
                'expires_at' => $expiresAt,
            ]);
            $metadata = [
                'pairing_code_id' => (string) $pairing->id,
                'provider' => $provider->value,
                'expires_at' => $expiresAt->toIso8601String(),
            ];
            $this->audit->record(
                'integration.external_actor.pairing_code_issued',
                $actor,
                $pairing,
                $alliance->allianceId,
                $metadata,
            );
            $this->outbox->record(
                'integration.external_actor.pairing_code_issued',
                $alliance->allianceId,
                $pairing,
                $metadata,
            );

            return new IssuedExternalActorPairingCode(
                pairingCodeId: (string) $pairing->id,
                provider: $provider->value,
                code: ExternalActorIdentity::formatPairingCode($rawCode),
                expiresAt: $expiresAt->toIso8601String(),
            );
        });
    }

    private function randomCode(): string
    {
        $code = '';
        $last = strlen(self::ALPHABET) - 1;
        for ($index = 0; $index < 12; $index++) {
            $code .= self::ALPHABET[random_int(0, $last)];
        }

        return $code;
    }
}

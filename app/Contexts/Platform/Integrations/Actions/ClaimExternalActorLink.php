<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Integrations\Actions;

use App\Contexts\Platform\Integrations\Enums\ExternalActorProvider;
use App\Contexts\Platform\Integrations\Models\ApiCredential;
use App\Contexts\Platform\Integrations\Models\ExternalActorLink;
use App\Contexts\Platform\Integrations\Models\ExternalActorPairingCode;
use App\Contexts\Platform\Integrations\Services\ExternalActorIdentity;
use App\Contexts\Platform\Integrations\ValueObjects\ExternalActorLinkReference;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ClaimExternalActorLink
{
    public function __construct(
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        string $allianceId,
        string $apiCredentialId,
        ExternalActorProvider $provider,
        string $externalSubject,
        string $pairingCode,
    ): ExternalActorLinkReference {
        $codeHash = ExternalActorIdentity::pairingCodeHash($pairingCode);
        $subjectHash = ExternalActorIdentity::subjectHash($provider, $externalSubject);
        $subjectHint = ExternalActorIdentity::subjectHint($externalSubject);

        return DB::transaction(function () use ($allianceId, $apiCredentialId, $provider, $codeHash, $subjectHash, $subjectHint): ExternalActorLinkReference {
            $credential = ApiCredential::query()
                ->whereKey($apiCredentialId)
                ->where('alliance_id', $allianceId)
                ->lockForUpdate()
                ->firstOrFail();
            if (! $credential->active() || ! $credential->allows('actor-links:write')) {
                throw ValidationException::withMessages(['credential' => 'The API credential cannot claim actor links.']);
            }

            $pairing = ExternalActorPairingCode::query()
                ->where('alliance_id', $allianceId)
                ->where('provider', $provider->value)
                ->where('code_hash', $codeHash)
                ->lockForUpdate()
                ->first();
            if (! $pairing instanceof ExternalActorPairingCode
                || $pairing->cancelled_at !== null
                || $pairing->expires_at->isPast()) {
                throw ValidationException::withMessages(['code' => 'The pairing code is invalid, expired, or already used.']);
            }

            $subjectLink = ExternalActorLink::query()
                ->where('alliance_id', $allianceId)
                ->where('provider', $provider->value)
                ->where('subject_hash', $subjectHash)
                ->lockForUpdate()
                ->first();
            if ($pairing->consumed_at !== null) {
                if ($subjectLink instanceof ExternalActorLink
                    && $subjectLink->player_id === $pairing->player_id
                    && $subjectLink->revoked_at === null) {
                    return new ExternalActorLinkReference(
                        linkId: (string) $subjectLink->id,
                        allianceId: (string) $subjectLink->alliance_id,
                        playerId: (string) $subjectLink->player_id,
                        provider: $provider->value,
                        subjectHint: (string) $subjectLink->subject_hint,
                    );
                }

                throw ValidationException::withMessages(['code' => 'The pairing code is invalid, expired, or already used.']);
            }
            if ($subjectLink instanceof ExternalActorLink && $subjectLink->player_id !== $pairing->player_id) {
                throw ValidationException::withMessages([
                    'external_subject' => 'This provider identity is already linked to another Governor.',
                ]);
            }

            $activePlayerLink = ExternalActorLink::query()
                ->where('alliance_id', $allianceId)
                ->where('player_id', $pairing->player_id)
                ->where('provider', $provider->value)
                ->whereNull('revoked_at')
                ->lockForUpdate()
                ->first();
            $replacedLinkId = null;
            if ($activePlayerLink instanceof ExternalActorLink
                && $activePlayerLink->subject_hash !== $subjectHash) {
                $activePlayerLink->forceFill(['revoked_at' => now()])->save();
                $replacedLinkId = (string) $activePlayerLink->id;
            }

            $link = $subjectLink instanceof ExternalActorLink
                ? $subjectLink
                : ($activePlayerLink instanceof ExternalActorLink && $replacedLinkId === null
                    ? $activePlayerLink
                    : new ExternalActorLink);
            $link->forceFill([
                'alliance_id' => $allianceId,
                'player_id' => $pairing->player_id,
                'api_credential_id' => $credential->id,
                'provider' => $provider,
                'subject_hash' => $subjectHash,
                'subject_hint' => $subjectHint,
                'verified_at' => now(),
                'revoked_at' => null,
            ])->save();
            $pairing->forceFill(['consumed_at' => now()])->save();

            $metadata = [
                'link_id' => (string) $link->id,
                'player_id' => (string) $link->player_id,
                'provider' => $provider->value,
                'api_credential_id' => (string) $credential->id,
                'replaced_link_id' => $replacedLinkId,
            ];
            $this->audit->record('integration.external_actor.linked', null, $link, $allianceId, $metadata);
            $this->outbox->record('integration.external_actor.linked', $allianceId, $link, $metadata);

            return new ExternalActorLinkReference(
                linkId: (string) $link->id,
                allianceId: (string) $link->alliance_id,
                playerId: (string) $link->player_id,
                provider: $provider->value,
                subjectHint: (string) $link->subject_hint,
            );
        });
    }
}

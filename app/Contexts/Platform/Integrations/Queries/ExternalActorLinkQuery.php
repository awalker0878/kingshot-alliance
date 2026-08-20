<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Integrations\Queries;

use App\Contexts\Platform\Integrations\Enums\ExternalActorProvider;
use App\Contexts\Platform\Integrations\Models\ExternalActorLink;
use App\Contexts\Platform\Integrations\Services\ExternalActorIdentity;
use App\Contexts\Platform\Integrations\ValueObjects\ExternalActorLinkReference;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class ExternalActorLinkQuery
{
    public function requireActive(
        string $allianceId,
        ExternalActorProvider $provider,
        string $externalSubject,
    ): ExternalActorLinkReference {
        $link = ExternalActorLink::query()
            ->where('alliance_id', $allianceId)
            ->where('provider', $provider->value)
            ->where('subject_hash', ExternalActorIdentity::subjectHash($provider, $externalSubject))
            ->whereNull('revoked_at')
            ->first();
        if (! $link instanceof ExternalActorLink) {
            throw (new ModelNotFoundException)->setModel(ExternalActorLink::class);
        }

        return new ExternalActorLinkReference(
            linkId: (string) $link->id,
            allianceId: (string) $link->alliance_id,
            playerId: (string) $link->player_id,
            provider: $link->provider->value,
            subjectHint: (string) $link->subject_hint,
        );
    }
}

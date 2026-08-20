<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Integrations\ValueObjects;

final readonly class ExternalActorLinkReference
{
    public function __construct(
        public string $linkId,
        public string $allianceId,
        public string $playerId,
        public string $provider,
        public string $subjectHint,
    ) {}
}

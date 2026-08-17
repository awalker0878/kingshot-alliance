<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\ValueObjects;

use App\Contexts\Alliance\Access\ValueObjects\AllianceAuthorityFacts;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;

/** Transaction-time authority state for Kingdom Transfer mutations. */
final readonly class TransferMutationContext
{
    public function __construct(
        public PlayerReference $actor,
        public AllianceAuthorityFacts $allianceAuthority,
    ) {}

    public function allianceId(): string
    {
        return $this->allianceAuthority->allianceId;
    }

    public function kingdomId(): string
    {
        return $this->allianceAuthority->kingdomId;
    }
}

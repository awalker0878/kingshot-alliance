<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Access\ValueObjects;

use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;

/**
 * Owner-internal transaction state. This object never crosses the Alliance
 * application boundary and may therefore carry locked Alliance Eloquent rows.
 */
final readonly class AllianceMutationContext
{
    public function __construct(
        public Alliance $alliance,
        public PlayerReference $actor,
        public AllianceMembership $membership,
    ) {}
}

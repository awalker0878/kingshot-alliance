<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Enums;

enum TransferRequirementState: string { case Met = 'met'; case Unmet = 'unmet'; case Unknown = 'unknown'; case Stale = 'stale'; case Conflicting = 'conflicting'; case NotApplicable = 'not_applicable'; }

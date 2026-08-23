<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Enums;

enum TransferCohortState: string { case Active = 'active'; case Archived = 'archived'; }

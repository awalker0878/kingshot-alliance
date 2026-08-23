<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Enums;

enum TransferRequirementKey: string { case WindowPhase = 'window_phase'; case TransferGroup = 'transfer_group'; case PowerCap = 'power_cap'; case Invitation = 'invitation'; case TransferPasses = 'transfer_passes'; case InGameRules = 'in_game_rules'; }

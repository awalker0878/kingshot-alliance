<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Enums;

enum TransferInvitationStatus: string { case None = 'none'; case OrdinaryReceived = 'ordinary_received'; case SpecialPending = 'special_pending'; case SpecialApproved = 'special_approved'; }

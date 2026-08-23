<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Enums;

enum TransferWindowPhase: string
{
    case NotStarted = 'not_started';
    case PreTransfer = 'pre_transfer';
    case InvitationalTransfer = 'invitational_transfer';
    case TransferOpens = 'transfer_opens';
    case Closed = 'closed';
}

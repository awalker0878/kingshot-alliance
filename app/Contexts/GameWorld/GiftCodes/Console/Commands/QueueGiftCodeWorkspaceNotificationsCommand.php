<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Console\Commands;

use App\Contexts\GameWorld\GiftCodes\Actions\QueueGiftCodeWorkspaceNotifications;
use Illuminate\Console\Command;

final class QueueGiftCodeWorkspaceNotificationsCommand extends Command
{
    protected $signature = 'gift-codes:queue-workspace-notifications {--limit=100}';

    protected $description = 'Advance one bounded cycle of Gift Code workspace notifications.';

    public function handle(QueueGiftCodeWorkspaceNotifications $action): int
    {
        $result = $action->cycle(max(1, min(500, (int) $this->option('limit'))));
        $this->line(json_encode($result, JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}

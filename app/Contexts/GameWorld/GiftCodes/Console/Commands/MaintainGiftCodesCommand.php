<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Console\Commands;

use App\Contexts\GameWorld\GiftCodes\Actions\ExpireGiftCodes;
use App\Contexts\GameWorld\GiftCodes\Actions\QueueGiftCodeExpiryNotifications;
use App\Contexts\GameWorld\GiftCodes\Actions\QueueGiftCodeTransitionNotifications;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

final class MaintainGiftCodesCommand extends Command
{
    protected $signature = 'gift-codes:maintain {--limit=100} {--after=} {--cycle}';

    protected $description = 'Reconcile Gift Code lifecycle and queue bounded idempotent notifications.';

    public function handle(ExpireGiftCodes $expire, QueueGiftCodeExpiryNotifications $expiryNotifications, QueueGiftCodeTransitionNotifications $transitionNotifications): int
    {
        $limit = max(1, min(500, (int) $this->option('limit')));
        $afterValue = $this->option('after');
        $afterOption = is_string($afterValue) ? trim($afterValue) : '';
        $cursorKey = 'gift-codes:expiry-notifications';
        $cycle = (bool) $this->option('cycle') && $afterOption === '';
        $storedCursor = $cycle ? Cache::get($cursorKey) : null;
        $after = $afterOption !== ''
            ? $afterOption
            : (is_string($storedCursor) && $storedCursor !== '' ? $storedCursor : null);

        $expired = $expire->handle($limit);
        $expiry = $expiryNotifications->handle($limit, $after);
        $transitionRuns = [];
        $transitionCampaignLimit = max(1, min(
            50,
            (int) config('game_world.gift_codes.transition_campaigns_per_run', 10),
        ));

        for ($campaign = 0; $campaign < $transitionCampaignLimit; $campaign++) {
            $transition = $transitionNotifications->handle($limit);
            if ($transition->examined === 0 && $transition->skipped === 0) {
                break;
            }

            $transitionRuns[] = $transition->toArray();
        }

        if ($cycle) {
            if ($expiry->nextCursor === null) {
                Cache::forget($cursorKey);
            } else {
                Cache::forever($cursorKey, $expiry->nextCursor);
            }
        }

        $this->line(json_encode([
            'expired' => $expired,
            'expiryNotifications' => $expiry->toArray(),
            'transitionNotifications' => [
                'campaignsProcessed' => count($transitionRuns),
                'runs' => $transitionRuns,
            ],
        ], JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}

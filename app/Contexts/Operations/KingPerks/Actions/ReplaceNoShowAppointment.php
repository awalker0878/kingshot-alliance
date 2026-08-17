<?php

declare(strict_types=1);

namespace App\Contexts\Operations\KingPerks\Actions;

use App\Contexts\Operations\KingPerks\Services\KingPerkScheduler;

final readonly class ReplaceNoShowAppointment
{
    public function __construct(private KingPerkScheduler $scheduler) {}

    public function handle(string $actorPlayerId, string $appointmentId, string $replacementPlayerId): void
    {
        $this->scheduler->replaceNoShowAppointment($actorPlayerId, $appointmentId, $replacementPlayerId);
    }
}

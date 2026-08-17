<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Events\ValueObjects;

use App\Contexts\Operations\Events\Enums\EventScope;

final readonly class EventTargetReference
{
    public function __construct(
        public EventScope $scope,
        public string $targetId,
        public ?string $allianceId,
        public ?string $kingdomId,
        public ?string $playerId,
        public string $displayName,
        public ?string $secondaryLabel,
        public string $timezone,
    ) {}

    /** @return array{alliance_id:?string,kingdom_id:?string,player_id:?string} */
    public function targetColumns(): array
    {
        return [
            'alliance_id' => $this->allianceId,
            'kingdom_id' => $this->scope === EventScope::Kingdom ? $this->kingdomId : null,
            'player_id' => $this->playerId,
        ];
    }

    /** @return array{target_display_name:string,target_secondary_label:?string} */
    public function historicalSnapshot(): array
    {
        return [
            'target_display_name' => $this->displayName,
            'target_secondary_label' => $this->secondaryLabel,
        ];
    }

    public function partitionKey(): string
    {
        return $this->scope->value.':'.$this->targetId;
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Events\Services;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Events\Enums\EventScope;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventTemplate;
use App\Domain\Kingdoms\Models\Kingdom;
use App\Domain\Kingdoms\Models\Player;
use Illuminate\Database\Eloquent\Model;
use LogicException;

final class EventTargetResolver
{
    public function scopeFor(Alliance|Kingdom|Player $target): EventScope
    {
        return match (true) {
            $target instanceof Alliance => EventScope::Alliance,
            $target instanceof Kingdom => EventScope::Kingdom,
            $target instanceof Player => EventScope::Player,
        };
    }

    /** @return array{alliance_id:?string,kingdom_id:?string,player_id:?string} */
    public function columnsFor(Alliance|Kingdom|Player $target): array
    {
        return [
            'alliance_id' => $target instanceof Alliance ? (string) $target->id : null,
            'kingdom_id' => $target instanceof Kingdom ? (string) $target->id : null,
            'player_id' => $target instanceof Player ? (string) $target->id : null,
        ];
    }

    public function defaultTimezone(Player $actor, Alliance|Kingdom|Player $target): string
    {
        return match (true) {
            $target instanceof Alliance => (string) $target->timezone,
            $target instanceof Kingdom => 'UTC',
            $target instanceof Player => 'UTC',
        };
    }

    public function resolve(EventScope $scope, string $targetId): Alliance|Kingdom|Player
    {
        return match ($scope) {
            EventScope::Alliance => Alliance::query()->whereKey($targetId)->firstOrFail(),
            EventScope::Kingdom => Kingdom::query()->whereKey($targetId)->firstOrFail(),
            EventScope::Player => Player::query()->whereKey($targetId)->firstOrFail(),
        };
    }

    public function forEvent(Event $event): Alliance|Kingdom|Player
    {
        return $this->forRecord($event);
    }

    public function forTemplate(EventTemplate $template): Alliance|Kingdom|Player
    {
        return $this->forRecord($template);
    }

    private function forRecord(Event|EventTemplate $record): Alliance|Kingdom|Player
    {
        $target = match ($record->scope) {
            EventScope::Alliance => $record->alliance()->first(),
            EventScope::Kingdom => $record->kingdom()->first(),
            EventScope::Player => $record->player()->first(),
        };

        if (! $target instanceof Model || ! $target instanceof Alliance && ! $target instanceof Kingdom && ! $target instanceof Player) {
            throw new LogicException('An event record must resolve exactly one valid target.');
        }

        return $target;
    }

    public function label(Alliance|Kingdom|Player $target): string
    {
        return match (true) {
            $target instanceof Alliance => (string) $target->name,
            $target instanceof Kingdom => '#'.(int) $target->number,
            $target instanceof Player => (string) $target->current_name,
        };
    }
}

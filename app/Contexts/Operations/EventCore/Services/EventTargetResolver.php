<?php

declare(strict_types=1);

namespace App\Contexts\Operations\EventCore\Services;

use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Operations\EventCore\Enums\EventScope;
use App\Contexts\Operations\EventCore\Models\Event;
use App\Contexts\Operations\EventCore\Models\EventTemplate;
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

    /** @return array{target_display_name:string,target_secondary_label:?string} */
    public function historicalSnapshotFor(Alliance|Kingdom|Player $target): array
    {
        if ($target instanceof Alliance) {
            $target->loadMissing('kingdom');

            return [
                'target_display_name' => (string) $target->name,
                'target_secondary_label' => 'Kingdom #'.(int) $target->kingdom->number,
            ];
        }

        if ($target instanceof Kingdom) {
            return [
                'target_display_name' => 'Kingdom #'.(int) $target->number,
                'target_secondary_label' => null,
            ];
        }

        $target->loadMissing('currentKingdom');

        return [
            'target_display_name' => (string) $target->current_name,
            'target_secondary_label' => 'Kingdom #'.(int) $target->currentKingdom->number,
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
        return $this->forRecord($event, $event->scopeEnum());
    }

    public function forTemplate(EventTemplate $template): Alliance|Kingdom|Player
    {
        return $this->forRecord($template, $template->scopeEnum());
    }

    private function forRecord(Event|EventTemplate $record, EventScope $scope): Alliance|Kingdom|Player
    {
        $target = match ($scope) {
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

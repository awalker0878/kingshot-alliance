<?php

declare(strict_types=1);

namespace App\ReadModels\EventManagement\Support;

use App\Contexts\Operations\Events\Models\Event;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\ReadModels\EventManagement\Enums\EventCommandItemStatus;
use App\ReadModels\EventManagement\Enums\EventCommandSeverity;

final class EventCommandItems
{
    /**
     * @param  array<string, int|string|null>  $parameters
     * @param  array{href:string,labelKey:string}|null  $handoff
     * @param  array<string, mixed>|null  $source
     * @return array<string, mixed>
     */
    public static function make(
        string $code,
        string $phase,
        EventCommandItemStatus $status,
        EventCommandSeverity $severity,
        string $owner,
        string $messageKey,
        array $parameters = [],
        ?int $count = null,
        string $classification = 'operational_fact',
        ?array $handoff = null,
        ?array $source = null,
    ): array {
        return [
            'code' => $code,
            'phase' => $phase,
            'status' => $status->value,
            'severity' => $severity->value,
            'owner' => $owner,
            'classification' => $classification,
            'count' => $count,
            'messageKey' => $messageKey,
            'messageParameters' => $parameters,
            'source' => $source,
            'handoff' => $handoff,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array{key:string,labelKey:string,phase:string,items:list<array<string,mixed>>}
     */
    public static function section(string $key, string $labelKey, string $phase, array $items): array
    {
        return [
            'key' => $key,
            'labelKey' => $labelKey,
            'phase' => $phase,
            'items' => array_values($items),
        ];
    }

    /** @return array{href:string,labelKey:string} */
    public static function handoff(Event $event, EventOccurrence $occurrence, string $anchor, string $labelKey): array
    {
        return ['href' => self::href($event, $occurrence, $anchor), 'labelKey' => $labelKey];
    }

    public static function href(Event $event, EventOccurrence $occurrence, string $anchor): string
    {
        $anchor = match ($anchor) {
            'participation' => 'participants',
            'territory' => 'territory-positioning',
            default => $anchor,
        };

        return '/events/'.(string) $event->id.'/manage?occurrence='.(string) $occurrence->id.'#'.$anchor;
    }

    /**
     * @param  list<array<string, mixed>>  $sections
     * @return list<array<string, mixed>>
     */
    public static function flatten(array $sections): array
    {
        $items = [];
        foreach ($sections as $section) {
            foreach (is_array($section['items'] ?? null) ? $section['items'] : [] as $item) {
                if (is_array($item)) {
                    $items[] = $item;
                }
            }
        }

        return $items;
    }

    /** @param  list<array<string, mixed>>  $items */
    public static function blockers(array $items): int
    {
        return count(array_filter(
            $items,
            static fn (array $item): bool => ($item['severity'] ?? null) === EventCommandSeverity::Blocking->value
                && in_array(
                    $item['status'] ?? null,
                    [EventCommandItemStatus::NeedsAttention->value, EventCommandItemStatus::Unknown->value],
                    true,
                ),
        ));
    }

    /** @param  list<array<string, mixed>>  $items */
    public static function warnings(array $items): int
    {
        return count(array_filter(
            $items,
            static fn (array $item): bool => ($item['severity'] ?? null) === EventCommandSeverity::Warning->value
                && in_array(
                    $item['status'] ?? null,
                    [EventCommandItemStatus::Warning->value, EventCommandItemStatus::Unknown->value],
                    true,
                ),
        ));
    }
}

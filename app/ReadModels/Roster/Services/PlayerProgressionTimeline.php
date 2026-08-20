<?php

declare(strict_types=1);

namespace App\ReadModels\Roster\Services;

use App\Contexts\Intelligence\Roster\Models\PlayerSnapshot;
use App\Contexts\Intelligence\Roster\Services\PowerMath;

final readonly class PlayerProgressionTimeline
{
    public function __construct(private PowerMath $powerMath) {}

    /**
     * Build consecutive changes for a newest-first snapshot sequence.
     *
     * @param iterable<int, PlayerSnapshot> $snapshots
     * @return array<string, array{
     *   fromCapturedAt: string,
     *   power: string,
     *   observedName: array{from: string|null, to: string|null}|null,
     *   progressionLevel: array{from: string|null, to: string|null}|null,
     *   observedAllianceTag: array{from: string|null, to: string|null}|null
     * }|null>
     */
    public function changes(iterable $snapshots): array
    {
        $ordered = [];
        foreach ($snapshots as $snapshot) {
            $ordered[] = $snapshot;
        }

        $changes = [];
        foreach ($ordered as $index => $current) {
            $previous = $ordered[$index + 1] ?? null;
            $changes[(string) $current->id] = $previous instanceof PlayerSnapshot
                ? [
                    'fromCapturedAt' => $previous->captured_at->toIso8601String(),
                    'power' => $this->powerMath->difference(
                        (string) $current->power,
                        (string) $previous->power,
                    ),
                    'observedName' => $this->textChange(
                        (string) $previous->observed_name,
                        (string) $current->observed_name,
                    ),
                    'progressionLevel' => $this->textChange(
                        $previous->progression_level,
                        $current->progression_level,
                    ),
                    'observedAllianceTag' => $this->textChange(
                        $previous->observed_alliance_tag,
                        $current->observed_alliance_tag,
                    ),
                ]
                : null;
        }

        return $changes;
    }

    /** @return array{from: string|null, to: string|null}|null */
    private function textChange(?string $from, ?string $to): ?array
    {
        return $from === $to ? null : ['from' => $from, 'to' => $to];
    }
}

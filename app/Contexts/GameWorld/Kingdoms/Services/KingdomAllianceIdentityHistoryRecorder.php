<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Kingdoms\Services;

use App\Contexts\GameWorld\Kingdoms\Enums\KingdomAllianceIdentitySource;
use App\Contexts\GameWorld\Kingdoms\Models\KingdomAlliance;
use App\Contexts\GameWorld\Kingdoms\Models\KingdomAllianceIdentityHistory;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

final class KingdomAllianceIdentityHistoryRecorder
{
    public function recordInitial(
        KingdomAlliance $alliance,
        KingdomAllianceIdentitySource $source = KingdomAllianceIdentitySource::Manual,
        ?string $sourceReference = null,
        ?DateTimeInterface $observedAt = null,
        ?int $confidenceBasisPoints = null,
        ?string $reason = null,
    ): KingdomAllianceIdentityHistory {
        $this->assertConfidence($confidenceBasisPoints);
        $at = now();

        return KingdomAllianceIdentityHistory::query()->create([
            'kingdom_alliance_id' => (string) $alliance->id,
            'name' => (string) $alliance->current_name,
            'tag' => $alliance->current_tag === null ? null : (string) $alliance->current_tag,
            'game_alliance_id' => $alliance->game_alliance_id === null ? null : (string) $alliance->game_alliance_id,
            'valid_from' => $at,
            'valid_to' => null,
            'source_type' => $source,
            'source_reference' => $this->nullable($sourceReference),
            'observed_at' => $observedAt === null ? null : Carbon::instance($observedAt)->utc(),
            'confidence_basis_points' => $confidenceBasisPoints,
            'reason' => $this->nullable($reason),
        ]);
    }

    public function transition(
        KingdomAlliance $alliance,
        string $name,
        ?string $tag,
        ?string $gameAllianceId,
        KingdomAllianceIdentitySource $source = KingdomAllianceIdentitySource::Manual,
        ?string $sourceReference = null,
        ?DateTimeInterface $observedAt = null,
        ?int $confidenceBasisPoints = null,
        ?string $reason = null,
    ): bool {
        $this->assertConfidence($confidenceBasisPoints);
        $name = trim($name);
        $tag = $this->nullable($tag);
        $gameAllianceId = $this->nullable($gameAllianceId);

        if ((string) $alliance->current_name === $name
            && ($alliance->current_tag === null ? null : (string) $alliance->current_tag) === $tag
            && ($alliance->game_alliance_id === null ? null : (string) $alliance->game_alliance_id) === $gameAllianceId) {
            return false;
        }

        $at = now();
        $current = KingdomAllianceIdentityHistory::query()
            ->where('kingdom_alliance_id', $alliance->id)
            ->whereNull('valid_to')
            ->lockForUpdate()
            ->first();

        if (! $current instanceof KingdomAllianceIdentityHistory) {
            $current = $this->recordInitial($alliance, $source, $sourceReference, $observedAt, $confidenceBasisPoints, $reason);
        }

        $current->forceFill(['valid_to' => $at])->save();
        KingdomAllianceIdentityHistory::query()->create([
            'kingdom_alliance_id' => (string) $alliance->id,
            'name' => $name,
            'tag' => $tag,
            'game_alliance_id' => $gameAllianceId,
            'valid_from' => $at,
            'valid_to' => null,
            'source_type' => $source,
            'source_reference' => $this->nullable($sourceReference),
            'observed_at' => $observedAt === null ? null : Carbon::instance($observedAt)->utc(),
            'confidence_basis_points' => $confidenceBasisPoints,
            'reason' => $this->nullable($reason),
        ]);

        return true;
    }

    public function closeCurrent(KingdomAlliance $alliance, ?DateTimeInterface $at = null): void
    {
        KingdomAllianceIdentityHistory::query()
            ->where('kingdom_alliance_id', $alliance->id)
            ->whereNull('valid_to')
            ->lockForUpdate()
            ->update(['valid_to' => $at === null ? now() : Carbon::instance($at)->utc()]);
    }

    private function nullable(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }

    private function assertConfidence(?int $confidenceBasisPoints): void
    {
        if ($confidenceBasisPoints !== null && ($confidenceBasisPoints < 0 || $confidenceBasisPoints > 10000)) {
            throw new InvalidArgumentException('Identity confidence must be between 0 and 10000 basis points.');
        }
    }
}

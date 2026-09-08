<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Kingdoms\Actions;

use App\Contexts\GameWorld\Kingdoms\Enums\KingdomAllianceIdentitySource;
use App\Contexts\GameWorld\Kingdoms\Enums\KingdomAllianceStatus;
use App\Contexts\GameWorld\Kingdoms\Enums\KingdomStatus;
use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Contexts\GameWorld\Kingdoms\Models\KingdomAlliance;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomAllianceReferenceQuery;
use App\Contexts\GameWorld\Kingdoms\Services\KingdomAllianceIdentityHistoryRecorder;
use App\Contexts\GameWorld\Kingdoms\ValueObjects\KingdomAllianceReference;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ResolveKingdomAlliance
{
    public function __construct(
        private KingdomAllianceReferenceQuery $references,
        private KingdomAllianceIdentityHistoryRecorder $history,
    ) {}

    public function handle(
        string $kingdomId,
        string $currentName,
        ?string $currentTag,
        ?string $gameAllianceId,
        KingdomAllianceIdentitySource $source = KingdomAllianceIdentitySource::Manual,
        ?string $sourceReference = null,
        ?DateTimeInterface $observedAt = null,
        ?int $confidenceBasisPoints = null,
    ): KingdomAllianceReference {
        $name = trim($currentName);
        if ($name === '') {
            throw ValidationException::withMessages(['current_name' => 'Alliance name is required.']);
        }

        $tag = $this->nullableLine($currentTag);
        $stableId = $this->nullableLine($gameAllianceId);

        $id = DB::transaction(function () use ($kingdomId, $name, $tag, $stableId, $source, $sourceReference, $observedAt, $confidenceBasisPoints): string {
            $kingdom = Kingdom::query()->whereKey($kingdomId)->lockForUpdate()->firstOrFail();
            if ($kingdom->status !== KingdomStatus::Active) {
                throw ValidationException::withMessages(['kingdom' => 'Archived Kingdoms cannot receive new or operational Alliance identities.']);
            }

            if ($stableId === null) {
                $alliance = KingdomAlliance::query()->create([
                    'kingdom_id' => $kingdomId,
                    'game_alliance_id' => null,
                    'current_name' => $name,
                    'current_tag' => $tag,
                    'status' => KingdomAllianceStatus::Active,
                    'canonical_kingdom_alliance_id' => null,
                ]);
                $this->history->recordInitial($alliance, $source, $sourceReference, $observedAt, $confidenceBasisPoints);

                return (string) $alliance->id;
            }

            $alliance = KingdomAlliance::query()->firstOrCreate(
                ['kingdom_id' => $kingdomId, 'game_alliance_id' => $stableId],
                [
                    'current_name' => $name,
                    'current_tag' => $tag,
                    'status' => KingdomAllianceStatus::Active,
                    'canonical_kingdom_alliance_id' => null,
                ],
            );

            if ($alliance->wasRecentlyCreated) {
                $this->history->recordInitial($alliance, $source, $sourceReference, $observedAt, $confidenceBasisPoints);
            }

            if ($alliance->status !== KingdomAllianceStatus::Active || $alliance->canonical_kingdom_alliance_id !== null) {
                throw ValidationException::withMessages([
                    'game_alliance_id' => 'That game Alliance identity is archived or has been reconciled to another canonical identity.',
                ]);
            }

            return (string) $alliance->id;
        });

        return $this->references->requireActive($id);
    }

    private function nullableLine(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}

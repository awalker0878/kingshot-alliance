<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Kingdoms\Actions;

use App\Contexts\GameWorld\Kingdoms\Enums\KingdomAllianceStatus;
use App\Contexts\GameWorld\Kingdoms\Enums\KingdomStatus;
use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Contexts\GameWorld\Kingdoms\Models\KingdomAlliance;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomReferenceQuery;
use App\Contexts\GameWorld\Kingdoms\ValueObjects\KingdomReference;
use App\Shared\Infrastructure\AuditTrail\Contracts\AuditActor;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;

final readonly class ArchiveKingdom
{
    public function __construct(
        private KingdomReferenceQuery $kingdoms,
        private AuditRecorder $audit,
    ) {}

    public function handle(string $kingdomId, ?AuditActor $actor = null, ?string $reason = null): KingdomReference
    {
        DB::transaction(function () use ($kingdomId, $actor, $reason): void {
            $kingdom = Kingdom::query()->whereKey($kingdomId)->lockForUpdate()->firstOrFail();
            if ($kingdom->status === KingdomStatus::Archived) {
                return;
            }

            $archivedCount = 0;
            $alliances = KingdomAlliance::query()
                ->where('kingdom_id', $kingdomId)
                ->where('status', KingdomAllianceStatus::Active->value)
                ->lockForUpdate()
                ->lazyById(200);

            foreach ($alliances as $alliance) {
                $alliance->forceFill(['status' => KingdomAllianceStatus::Archived])->save();
                $this->audit->record('kingdoms.alliance_archived', $actor, $alliance, null, [
                    'kingdom_id' => $kingdomId,
                    'reason' => $reason,
                    'cascade_from_kingdom' => true,
                ]);
                $archivedCount++;
            }

            $kingdom->forceFill(['status' => KingdomStatus::Archived])->save();
            $this->audit->record('kingdoms.kingdom_archived', $actor, $kingdom, null, [
                'reason' => $reason,
                'archived_alliance_count' => $archivedCount,
            ]);
        });

        return $this->kingdoms->require($kingdomId);
    }
}

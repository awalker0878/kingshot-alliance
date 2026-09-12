<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Kingdoms\Actions;

use App\Contexts\GameWorld\Kingdoms\Enums\KingdomAllianceStatus;
use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Contexts\GameWorld\Kingdoms\Models\KingdomAlliance;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomAllianceReferenceQuery;
use App\Contexts\GameWorld\Kingdoms\ValueObjects\KingdomAllianceReference;
use App\Shared\Infrastructure\AuditTrail\Contracts\AuditActor;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;

final readonly class ArchiveKingdomAlliance
{
    public function __construct(
        private KingdomAllianceReferenceQuery $alliances,
        private AuditRecorder $audit,
    ) {}

    public function handle(string $kingdomAllianceId, ?AuditActor $actor = null, ?string $reason = null): KingdomAllianceReference
    {
        DB::transaction(function () use ($kingdomAllianceId, $actor, $reason): void {
            $candidate = KingdomAlliance::query()->whereKey($kingdomAllianceId)->firstOrFail(['id', 'kingdom_id']);
            $kingdom = Kingdom::query()->whereKey($candidate->kingdom_id)->lockForUpdate()->firstOrFail();
            $alliance = KingdomAlliance::query()->whereKey($kingdomAllianceId)->where('kingdom_id', $kingdom->id)->lockForUpdate()->firstOrFail();
            if ($alliance->status === KingdomAllianceStatus::Archived) {
                return;
            }

            $alliance->forceFill(['status' => KingdomAllianceStatus::Archived])->save();
            $this->audit->record('kingdoms.alliance_archived', $actor, $alliance, null, [
                'kingdom_id' => (string) $alliance->kingdom_id,
                'reason' => $reason,
                'cascade_from_kingdom' => false,
            ]);
        });

        return $this->alliances->require($kingdomAllianceId);
    }
}

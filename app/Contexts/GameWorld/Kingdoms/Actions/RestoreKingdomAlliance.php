<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Kingdoms\Actions;

use App\Contexts\GameWorld\Kingdoms\Enums\KingdomAllianceStatus;
use App\Contexts\GameWorld\Kingdoms\Enums\KingdomStatus;
use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Contexts\GameWorld\Kingdoms\Models\KingdomAlliance;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomAllianceReferenceQuery;
use App\Contexts\GameWorld\Kingdoms\ValueObjects\KingdomAllianceReference;
use App\Shared\Infrastructure\AuditTrail\Contracts\AuditActor;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class RestoreKingdomAlliance
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
            if ($alliance->canonical_kingdom_alliance_id !== null) {
                throw ValidationException::withMessages([
                    'kingdom_alliance' => 'A reconciled alias cannot be restored. Use its canonical Alliance identity.',
                ]);
            }

            if ($kingdom->status !== KingdomStatus::Active) {
                throw ValidationException::withMessages([
                    'kingdom' => 'An Alliance identity cannot be restored while its Kingdom is archived.',
                ]);
            }

            if ($alliance->status === KingdomAllianceStatus::Active) {
                return;
            }

            $alliance->forceFill(['status' => KingdomAllianceStatus::Active])->save();
            $this->audit->record('kingdoms.alliance_restored', $actor, $alliance, null, [
                'kingdom_id' => (string) $alliance->kingdom_id,
                'reason' => $reason,
            ]);
        });

        return $this->alliances->requireActive($kingdomAllianceId);
    }
}

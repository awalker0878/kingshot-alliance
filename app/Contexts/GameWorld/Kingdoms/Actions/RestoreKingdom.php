<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Kingdoms\Actions;

use App\Contexts\GameWorld\Kingdoms\Enums\KingdomStatus;
use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomReferenceQuery;
use App\Contexts\GameWorld\Kingdoms\ValueObjects\KingdomReference;
use App\Shared\Infrastructure\AuditTrail\Contracts\AuditActor;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;

final readonly class RestoreKingdom
{
    public function __construct(
        private KingdomReferenceQuery $kingdoms,
        private AuditRecorder $audit,
    ) {}

    public function handle(string $kingdomId, ?AuditActor $actor = null, ?string $reason = null): KingdomReference
    {
        DB::transaction(function () use ($kingdomId, $actor, $reason): void {
            $kingdom = Kingdom::query()->whereKey($kingdomId)->lockForUpdate()->firstOrFail();
            if ($kingdom->status === KingdomStatus::Active) {
                return;
            }

            $kingdom->forceFill(['status' => KingdomStatus::Active])->save();
            $this->audit->record('kingdoms.kingdom_restored', $actor, $kingdom, null, [
                'reason' => $reason,
                'child_alliances_restored' => false,
            ]);
        });

        return $this->kingdoms->requireActive($kingdomId);
    }
}

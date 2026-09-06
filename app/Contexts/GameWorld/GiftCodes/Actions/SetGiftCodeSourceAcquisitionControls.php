<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Actions;

use App\Contexts\Accounts\Identity\ValueObjects\AccountIdentity;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceSmokeCheck;
use App\Contexts\Platform\Administration\Services\PlatformAuthorization;
use Illuminate\Validation\ValidationException;

final readonly class SetGiftCodeSourceAcquisitionControls
{
    public function __construct(
        private ManageGiftCodeSourceRegistry $sources,
        private PlatformAuthorization $platformAuthorization,
    ) {}

    /** @param array{ingestion_enabled:bool,push_enabled:bool,head_poll_enabled:bool,reconciliation_enabled:bool,backfill_enabled:bool,authority_promotion_enabled:bool} $controls */
    public function handle(AccountIdentity $actor, string $sourceId, array $controls): string
    {
        abort_unless($this->platformAuthorization->allows($actor), 403);
        $source = GiftCodeSourceRegistry::query()->findOrFail($sourceId);
        $activating = ! $source->ingestion_enabled && $controls['ingestion_enabled'];
        if ($activating) {
            $maximumAgeHours = max(1, min(168, (int) config('game_world.gift_codes.source_smoke_check_max_age_hours', 24)));
            $smoke = GiftCodeSourceSmokeCheck::query()
                ->where('gift_code_source_id', $source->id)
                ->orderByDesc('checked_at')
                ->first();
            if (! $smoke instanceof GiftCodeSourceSmokeCheck
                || $smoke->status !== 'passed'
                || $smoke->checked_at->lt(now()->subHours($maximumAgeHours))) {
                throw ValidationException::withMessages([
                    'ingestion_enabled' => sprintf('Run a passing source smoke check within %d hours before enabling acquisition.', $maximumAgeHours),
                ]);
            }
        }
        if ($source->classification === 'independent' && $controls['authority_promotion_enabled']) {
            throw ValidationException::withMessages([
                'authority_promotion_enabled' => 'Independent sources cannot enable authority promotion.',
            ]);
        }

        return $this->sources->register($actor, [
            'source_key' => $source->source_key,
            'name' => $source->name,
            'classification' => $source->classification,
            'canonical_domain' => (string) $source->canonical_domain,
            'verification_method' => $source->verification_method,
            'adapter_key' => $source->adapter_key,
            'provenance_policy' => $source->provenance_policy ?? [],
            'ingestion_enabled' => $controls['ingestion_enabled'],
            'push_enabled' => $controls['push_enabled'],
            'head_poll_enabled' => $controls['head_poll_enabled'],
            'reconciliation_enabled' => $controls['reconciliation_enabled'],
            'backfill_enabled' => $controls['backfill_enabled'],
            'authority_promotion_enabled' => $controls['authority_promotion_enabled'],
        ]);
    }
}

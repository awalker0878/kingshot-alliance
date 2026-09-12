<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Integrations\Queries;

use App\Contexts\Platform\Integrations\Models\ApiCredential;
use App\Contexts\Platform\Integrations\Models\WebhookSubscription;
use Illuminate\Database\Eloquent\Builder;

final class IntegrationUsageQuery
{
    public function activeCredentials(string $allianceId): int
    {
        return $this->credentials()->where('alliance_id', $allianceId)->count();
    }

    public function activeWebhooks(string $allianceId): int
    {
        return $this->webhooks()->where('alliance_id', $allianceId)->count();
    }

    /**
     * @param  list<string>  $allianceIds
     * @return array<string,array{activeCredentials:int,activeWebhooks:int}>
     */
    public function forAlliances(array $allianceIds): array
    {
        if ($allianceIds === []) {
            return [];
        }
        $credentials = $this->credentials()->whereIn('alliance_id', $allianceIds)
            ->selectRaw('alliance_id, count(*) AS total')->groupBy('alliance_id')->pluck('total', 'alliance_id');
        $webhooks = $this->webhooks()->whereIn('alliance_id', $allianceIds)
            ->selectRaw('alliance_id, count(*) AS total')->groupBy('alliance_id')->pluck('total', 'alliance_id');
        $result = [];
        foreach ($allianceIds as $id) {
            $result[$id] = ['activeCredentials' => (int) $credentials->get($id, 0), 'activeWebhooks' => (int) $webhooks->get($id, 0)];
        }

        return $result;
    }

    /** @return Builder<ApiCredential> */
    private function credentials(): Builder
    {
        return ApiCredential::query()->whereNull('revoked_at')
            ->where(static fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    /** @return Builder<WebhookSubscription> */
    private function webhooks(): Builder
    {
        return WebhookSubscription::query()->where('is_active', true)->whereNull('revoked_at');
    }
}

<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Integrations\Queries;

use App\Contexts\Platform\Integrations\Models\ApiCredential;
use App\Contexts\Platform\Integrations\Models\WebhookSubscription;

final class IntegrationUsageQuery
{
    public function activeCredentials(string $allianceId): int
    {
        return ApiCredential::query()->where('alliance_id', $allianceId)->whereNull('revoked_at')
            ->where(static fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))->count();
    }

    public function activeWebhooks(string $allianceId): int
    {
        return WebhookSubscription::query()->where('alliance_id', $allianceId)
            ->where('is_active', true)->whereNull('revoked_at')->count();
    }
}

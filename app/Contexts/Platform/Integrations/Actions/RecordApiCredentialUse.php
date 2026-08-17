<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Integrations\Actions;

use App\Contexts\Platform\Integrations\Models\ApiCredential;
use Illuminate\Support\Facades\DB;

final class RecordApiCredentialUse
{
    public function handle(string $credentialId): void
    {
        $credential = ApiCredential::query()->whereKey($credentialId)->firstOrFail();

        if ($credential->last_used_at !== null && $credential->last_used_at->gte(now()->subMinutes(5))) {
            return;
        }

        DB::transaction(function () use ($credential): void {
            $locked = ApiCredential::query()->whereKey($credential->id)->lockForUpdate()->firstOrFail();

            if ($locked->last_used_at === null || $locked->last_used_at->lt(now()->subMinutes(5))) {
                $locked->forceFill(['last_used_at' => now()])->save();
            }
        });
    }
}

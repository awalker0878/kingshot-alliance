<?php

declare(strict_types=1);

namespace App\Contexts\Platform\AllianceAdministration\Queries;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PlanEntitlementQuery
{
    public const DEFAULT_PLAN_CODE = 'standard';

    public function limit(string $allianceId, string $key): int
    {
        $value = DB::table('platform_plan_entitlements')
            ->where('plan_code', $this->planCode($allianceId))
            ->where('entitlement_key', $key)
            ->value('limit_value');

        return $this->value($value, $key);
    }

    /** @return array{members:int,storageBytes:int,apiCredentials:int,webhookSubscriptions:int} */
    public function limits(string $allianceId): array
    {
        $keys = [
            'members' => 'members.max',
            'storageBytes' => 'storage.bytes.max',
            'apiCredentials' => 'api_credentials.max',
            'webhookSubscriptions' => 'webhook_subscriptions.max',
        ];
        $values = DB::table('platform_plan_entitlements')
            ->where('plan_code', $this->planCode($allianceId))
            ->whereIn('entitlement_key', array_values($keys))
            ->pluck('limit_value', 'entitlement_key');

        return [
            'members' => $this->value($values->get($keys['members']), $keys['members']),
            'storageBytes' => $this->value($values->get($keys['storageBytes']), $keys['storageBytes']),
            'apiCredentials' => $this->value($values->get($keys['apiCredentials']), $keys['apiCredentials']),
            'webhookSubscriptions' => $this->value($values->get($keys['webhookSubscriptions']), $keys['webhookSubscriptions']),
        ];
    }

    private function planCode(string $allianceId): string
    {
        $code = DB::table('alliance_plan_assignments')->where('alliance_id', $allianceId)->value('plan_code');

        return is_string($code) && $code !== '' ? $code : self::DEFAULT_PLAN_CODE;
    }

    private function value(mixed $value, string $key): int
    {
        if (! is_numeric($value)) {
            throw ValidationException::withMessages(['plan' => sprintf('The current plan does not define the %s entitlement.', $key)]);
        }

        return max(0, (int) $value);
    }
}

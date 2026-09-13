<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Services;

use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlanAccessGrant;
use App\Contexts\Operations\TerritoryPlanning\ValueObjects\TerritoryPlanMutationContext;
use Illuminate\Auth\Access\AuthorizationException;

/** Grants narrow editing/review within existing current scope; they never confer membership or viewing rights. */
final readonly class TerritoryCollaborationAuthorization
{
    public function __construct(private TerritoryPlanningAuthorization $authorization, private TerritoryPlanSnapshotBuilder $snapshots) {}

    public function authorizeReview(TerritoryPlanMutationContext $context, ?string $allianceKey = null): void
    {
        $this->authorization->authorizeView($context);
        if ($this->isManager($context)) {
            return;
        }
        $required = $allianceKey === null
            ? $context->plan->planAlliances()->pluck('plan_key')->all() : [$allianceKey];
        $granted = $this->layers($context, ['review', 'edit']);
        if ($required === [] || array_diff($required, $granted) !== []) {
            throw new AuthorizationException;
        }
    }

    /** @param array<int,array<string,mixed>> $alliances
     * @param  array<int,array<string,mixed>>  $groups
     * @param  array<int,array<string,mixed>>  $objects
     * @param  array<string,mixed>  $preferences
     */
    public function authorizeLayout(TerritoryPlanMutationContext $context, array $alliances, array $groups, array $objects, array $preferences): void
    {
        $this->authorization->authorizeView($context);
        if ($this->isManager($context)) {
            return;
        }
        $layers = $this->layers($context, ['edit']);
        if ($layers === []) {
            throw new AuthorizationException;
        }
        $snapshot = $this->snapshots->build($context->plan);
        // Shared identity, grouping and preferences are whole-plan management operations.
        if ($this->canonical($alliances) !== $this->canonical($snapshot['alliances'])
            || $this->canonical($groups) !== $this->canonical($snapshot['groups'])
            || $this->canonical($preferences) !== $this->canonical($context->plan->planning_preferences ?? [])) {
            throw new AuthorizationException;
        }
        $protected = static fn (array $rows): array => array_values(array_filter($rows,
            static fn (array $row): bool => ! in_array($row['alliance_key'] ?? null, $layers, true)));
        if ($this->canonical($protected($objects)) !== $this->canonical($protected($snapshot['objects']))) {
            throw new AuthorizationException;
        }
        // A key cannot be moved out of a protected layer by renaming its Alliance reference.
        $protectedKeys = array_column($protected($snapshot['objects']), 'key');
        foreach ($objects as $object) {
            if (in_array($object['key'] ?? null, $protectedKeys, true) && in_array($object['alliance_key'] ?? null, $layers, true)) {
                throw new AuthorizationException;
            }
        }
    }

    public function isManager(TerritoryPlanMutationContext $context): bool
    {
        try {
            $this->authorization->authorizeManage($context);

            return true;
        } catch (AuthorizationException) {
            return false;
        }
    }

    /**
     * @param  list<string>  $permissions
     * @return list<string>
     */
    private function layers(TerritoryPlanMutationContext $context, array $permissions): array
    {
        return array_values(TerritoryPlanAccessGrant::query()->where('territory_plan_id', $context->plan->id)
            ->where('player_id', $context->actor->playerId)->whereIn('permission', $permissions)
            ->whereNull('revoked_at')->where('expires_at', '>', now())->get()->map(static fn (TerritoryPlanAccessGrant $grant): string => $grant->alliance_key)->values()->all());
    }

    private function canonical(mixed $value): string
    {
        $normalize = function (mixed $item) use (&$normalize): mixed {
            if (! is_array($item)) {
                return $item;
            }
            if (array_is_list($item)) {
                $item = array_map($normalize, $item);
                if ($item !== [] && is_array($item[0]) && isset($item[0]['key'])) {
                    usort($item, static fn (array $a, array $b): int => strcmp((string) $a['key'], (string) $b['key']));
                }
            } else {
                ksort($item);
                $item = array_map($normalize, $item);
            }

            return $item;
        };

        return json_encode($normalize($value), JSON_THROW_ON_ERROR);
    }
}

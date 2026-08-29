<?php

declare(strict_types=1);

namespace App\ReadModels\NotificationDelivery\Queries;

use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Lifecycle\Enums\AllianceStatus;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\ReadModels\NotificationDelivery\ValueObjects\AllianceNotificationRecipient;
use App\ReadModels\NotificationDelivery\ValueObjects\AllianceNotificationRecipientPage;
use Illuminate\Database\Eloquent\Builder;

final readonly class AllianceNotificationRecipientQuery
{
    private const MAX_LIMIT = 2000;

    public function __construct(
        private PlayerReferenceQuery $players,
        private AccountIdentityQuery $accounts,
    ) {}

    public function officers(int $limit, ?string $afterMembershipId = null): AllianceNotificationRecipientPage
    {
        return $this->page($limit, $afterMembershipId, true);
    }

    public function intelligenceMembers(int $limit, ?string $afterMembershipId = null): AllianceNotificationRecipientPage
    {
        return $this->page($limit, $afterMembershipId, false);
    }

    private function page(
        int $limit,
        ?string $afterMembershipId,
        bool $officersOnly,
    ): AllianceNotificationRecipientPage {
        $limit = max(1, min(self::MAX_LIMIT, $limit));
        $rows = AllianceMembership::query()
            ->select(['id', 'alliance_id', 'player_id'])
            ->where('status', MembershipStatus::Active->value)
            ->whereHas('alliance', static fn (Builder $query) => $query
                ->where('status', AllianceStatus::Active->value))
            ->when($afterMembershipId !== null && $afterMembershipId !== '', static fn (Builder $query) => $query
                ->where('id', '>', $afterMembershipId))
            ->when($officersOnly, static fn (Builder $query) => $query
                ->where(static fn (Builder $authority) => $authority
                    ->whereIn('rank', [AllianceRank::R4->value, AllianceRank::R5->value])
                    ->orWhereHas('roles.permissions', static fn (Builder $permission) => $permission
                        ->where('permissions.key', AlliancePermission::MembershipManage->key()))))
            ->orderBy('id')
            ->limit($limit + 1)
            ->get();

        $truncated = $rows->count() > $limit;
        $pageRows = $rows->take($limit)->values();
        $playerIds = array_values($pageRows->pluck('player_id')->map(
            static fn (mixed $id): string => (string) $id,
        )->all());
        $players = $this->players->byIds($playerIds);
        $userIds = array_values(array_filter(array_map(
            static fn (string $playerId): ?int => $players[$playerId]->userId ?? null,
            $playerIds,
        )));
        $accounts = $this->accounts->byIds($userIds);
        $recipients = [];

        foreach ($pageRows as $membership) {
            $player = $players[(string) $membership->player_id] ?? null;
            if ($player === null || $player->userId === null) {
                continue;
            }
            $account = $accounts[$player->userId] ?? null;
            if ($account === null || $account->anonymized) {
                continue;
            }

            $recipients[] = new AllianceNotificationRecipient(
                membershipId: (string) $membership->id,
                allianceId: (string) $membership->alliance_id,
                player: $player,
                timezone: trim($account->timezone) !== '' ? $account->timezone : 'UTC',
            );
        }

        $last = $pageRows->last();

        return new AllianceNotificationRecipientPage(
            recipients: $recipients,
            examinedCount: $pageRows->count(),
            nextCursor: $truncated && $last instanceof AllianceMembership ? (string) $last->id : null,
            truncated: $truncated,
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Workflows\NotificationDelivery\Services;

use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Accounts\Security\Queries\SecurityNotificationEligibilityQuery;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Content\Queries\AnnouncementNotificationEligibilityQuery;
use App\Contexts\Communications\Delivery\Contracts\NotificationSourceAuthorization;
use App\Contexts\Communications\Delivery\Queries\EndpointTestNotificationEligibilityQuery;
use App\Contexts\Communications\Delivery\ValueObjects\NotificationSource;
use App\Contexts\GameWorld\GiftCodes\Queries\GiftCodeNotificationEligibilityQuery;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceAuthorization;
use App\Contexts\Operations\KingPerks\Queries\KingPerkNotificationEligibilityQuery;
use App\Contexts\Operations\Participation\Reminders\Queries\EventReminderNotificationEligibilityQuery;
use App\Contexts\Platform\Administration\Services\PlatformAuthorization;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/** Current owner authorization, never a grant inferred from a cached notification body. */
final readonly class CurrentNotificationSourceAuthorization implements NotificationSourceAuthorization
{
    public function __construct(
        private AccountIdentityQuery $accounts,
        private PlayerReferenceQuery $players,
        private SecurityNotificationEligibilityQuery $security,
        private AllianceAuthorization $alliances,
        private AllianceIntelligenceAuthorization $intelligence,
        private AnnouncementNotificationEligibilityQuery $announcements,
        private EventReminderNotificationEligibilityQuery $events,
        private KingPerkNotificationEligibilityQuery $kingPerks,
        private GiftCodeNotificationEligibilityQuery $giftCodes,
        private PlatformAuthorization $platform,
        private EndpointTestNotificationEligibilityQuery $endpointTests,
    ) {}

    public function allows(NotificationSource $source): bool
    {
        $account = $this->accounts->find($source->recipientUserId);
        if ($account === null || $account->anonymized) {
            return false;
        }
        $player = $source->playerId === null ? null : $this->players->findOwnedByUser($source->recipientUserId, $source->playerId);
        if ($source->playerId !== null && $player === null) {
            return false;
        }
        $allianceId = $source->metadataString('alliance_id');

        try {
            return match ($source->notificationType) {
                'account.security' => $this->security->allows($source),
                'communications.endpoint_test' => $this->endpointTests->allows($source),
                'alliance.announcement' => $player !== null && $this->announcements->allows($source, $player),
                'event.reminder' => $player !== null && $this->events->allows($source, $player),
                'king_perks.reminder' => $player !== null && $this->kingPerks->allows($source, $player),
                'officer.brief' => $player !== null && $allianceId !== null && $source->subjectType === 'officer_brief'
                    && $source->subjectId !== null && $this->alliances->canManageMembership($player->playerId, $allianceId),
                'intelligence.change' => $player !== null && $allianceId !== null && $source->subjectId !== null
                    && $this->intelligence->canView($player->playerId, $allianceId),
                'gift_code.expiring', 'gift_code.available', 'gift_code.trust_changed', 'gift_code.reminder' => $this->giftCodes->allowsCatalogue($source),
                'gift_code.redemption_ready' => $this->giftCodes->allowsWorkspace($source),
                'gift_code.source_alert' => $this->platform->allows($account) && $this->giftCodes->operationalSourceAvailable($source),
                default => false,
            };
        } catch (ModelNotFoundException|AuthorizationException) {
            // Deleted/revoked source facts deny publication. Infrastructure failures still propagate.
            return false;
        }
    }
}

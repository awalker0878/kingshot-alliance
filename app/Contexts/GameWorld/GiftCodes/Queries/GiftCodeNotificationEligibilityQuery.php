<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Queries;

use App\Contexts\Communications\Delivery\ValueObjects\NotificationSource;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCode;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;

/** Catalogue facts are public to the intended account; named Governor facts are not. */
final readonly class GiftCodeNotificationEligibilityQuery
{
    public function __construct(private PlayerReferenceQuery $players) {}

    public function allowsCatalogue(NotificationSource $source): bool
    {
        if ($source->subjectType !== 'gift_code' || $source->subjectId === null
            || ! GiftCode::query()->whereKey($source->subjectId)->exists()) {
            return false;
        }
        if ($source->notificationType === 'gift_code.expiring') {
            return $source->playerId !== null;
        }
        if ($source->notificationType === 'gift_code.reminder') {
            return $this->players->ownedByUserUpTo($source->recipientUserId, 1) !== [];
        }
        $governors = $source->metadata['governors'] ?? null;
        if (! is_array($governors) || $governors === [] || count($governors) > 50) {
            return false;
        }
        $ids = [];
        foreach ($governors as $governor) {
            if (! is_array($governor) || ! is_string($governor['id'] ?? null) || strlen($governor['id']) !== 26) {
                return false;
            }
            $ids[] = $governor['id'];
        }
        $references = $this->players->byIds($ids);
        foreach ($ids as $id) {
            $player = $references[$id] ?? null;
            if ($player === null || ! $player->directIdentity() || $player->userId !== $source->recipientUserId) {
                return false;
            }
        }

        return true;
    }

    public function allowsWorkspace(NotificationSource $source): bool
    {
        return $source->playerId === null && $source->subjectType === 'gift_code_workspace'
            && $source->subjectId === (string) $source->recipientUserId
            && $this->players->ownedByUserUpTo($source->recipientUserId, 1) !== [];
    }

    public function operationalSourceAvailable(NotificationSource $source): bool
    {
        return $source->playerId === null && $source->subjectType === 'gift_code_source'
            && $source->subjectId !== null && $source->metadataString('source_id') === $source->subjectId
            && GiftCodeSourceRegistry::query()->whereKey($source->subjectId)->where('is_active', true)
                ->where('ingestion_enabled', true)->whereNull('revoked_at')->exists();
    }
}

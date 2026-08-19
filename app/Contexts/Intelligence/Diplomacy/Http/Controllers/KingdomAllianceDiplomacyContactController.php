<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Diplomacy\Http\Controllers;

use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomAllianceReferenceQuery;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomReferenceQuery;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceAuthorization;
use App\Contexts\Intelligence\Diplomacy\Actions\DeactivateKingdomAllianceDiplomacyContact;
use App\Contexts\Intelligence\Diplomacy\Actions\SaveKingdomAllianceDiplomacyContact;
use App\Contexts\Intelligence\Diplomacy\Enums\KingdomAllianceContactChannel;
use App\Contexts\Intelligence\Diplomacy\Models\KingdomAllianceDiplomacyContact;
use App\Contexts\Intelligence\Diplomacy\Queries\KingdomAllianceDiplomacyContactQuery;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;
use Inertia\Inertia;
use Inertia\Response;

final class KingdomAllianceDiplomacyContactController extends Controller
{
    public function show(
        Request $request,
        AllianceContext $context,
        AllianceIntelligenceAuthorization $authorization,
        KingdomAllianceDiplomacyContactQuery $contacts,
        AllianceReferenceQuery $alliances,
        KingdomReferenceQuery $kingdoms,
        KingdomAllianceReferenceQuery $kingdomAlliances,
        PlayerReferenceQuery $players,
        AccountIdentityQuery $accounts,
        string $tracking,
    ): Response {
        $scope = $context->scope();
        if (! $authorization->allows($scope->playerId, $scope->allianceId, IntelligencePermission::KingdomManage)) {
            throw new AuthorizationException;
        }
        $account = $accounts->require((int) $request->user()?->getAuthIdentifier());
        $alliance = $alliances->require($scope->allianceId);
        $allianceKingdom = $kingdoms->require($alliance->kingdomId);
        $tracked = $contacts->tracking($alliance->allianceId, $tracking);
        $trackedKingdom = $kingdoms->require((string) $tracked->kingdom_id);
        $trackedAlliance = $kingdomAlliances->require((string) $tracked->kingdom_alliance_id);
        $records = $contacts->contacts($alliance->allianceId, $tracking);
        $actorIds = [];
        foreach ($records as $contact) {
            foreach ([$contact->created_by_player_id, $contact->updated_by_player_id, $contact->deactivated_by_player_id] as $id) {
                if ($id !== null) {
                    $actorIds[] = (string) $id;
                }
            }
        }
        $actorRefs = $players->byIds(array_values(array_unique($actorIds)));

        return Inertia::render('Intelligence/KingdomWatch/Envoys', [
            'user' => ['name' => $account->name, 'email' => $account->email],
            'alliance' => ['id' => $alliance->allianceId, 'name' => $alliance->name, 'kingdom' => (string) $allianceKingdom->number],
            'tracking' => [
                'id' => (string) $tracked->id,
                'name' => $trackedAlliance->currentName,
                'tag' => $trackedAlliance->currentTag,
                'state' => $tracked->state->value,
                'kingdom' => (string) $trackedKingdom->number,
                'contextCurrent' => $alliance->kingdomId === $tracked->kingdom_id,
            ],
            'channels' => [
                ['value' => KingdomAllianceContactChannel::InGame->value, 'label' => 'In-game'],
                ['value' => KingdomAllianceContactChannel::Discord->value, 'label' => 'Discord'],
                ['value' => KingdomAllianceContactChannel::OtherHandle->value, 'label' => 'Other handle/channel'],
            ],
            'contactLimit' => KingdomAllianceDiplomacyContactQuery::CONTACT_LIMIT,
            'contacts' => $records->map(fn (KingdomAllianceDiplomacyContact $contact): array => $this->contactRow($contact, $actorRefs))->values(),
        ]);
    }

    public function store(Request $request, AllianceContext $context, SaveKingdomAllianceDiplomacyContact $save, string $tracking): RedirectResponse
    {
        $scope = $context->scope();
        $save->handle($scope->allianceId, $scope->playerId, $tracking, $this->validated($request));

        return back()->with('status', 'kingdom-alliance-diplomacy-contact-saved');
    }

    public function update(Request $request, AllianceContext $context, SaveKingdomAllianceDiplomacyContact $save, string $tracking, string $contact): RedirectResponse
    {
        $scope = $context->scope();
        $save->handle($scope->allianceId, $scope->playerId, $tracking, $this->validated($request), $contact);

        return back()->with('status', 'kingdom-alliance-diplomacy-contact-saved');
    }

    public function deactivate(Request $request, AllianceContext $context, DeactivateKingdomAllianceDiplomacyContact $deactivate, string $tracking, string $contact): RedirectResponse
    {
        $scope = $context->scope();
        $deactivate->handle($scope->allianceId, $scope->playerId, $tracking, $contact);

        return back()->with('status', 'kingdom-alliance-diplomacy-contact-deactivated');
    }

    /** @return array{display_name:string,game_role?:string|null,channel_type:string,handle:string,last_verified_at?:string|null,manager_notes?:string|null} */
    private function validated(Request $request): array
    {
        /** @var array{display_name:string,game_role?:string|null,channel_type:string,handle:string,last_verified_at?:string|null,manager_notes?:string|null} $validated */
        $validated = $request->validate([
            'display_name' => ['required', 'string', 'max:160'],
            'game_role' => ['nullable', 'string', 'max:120'],
            'channel_type' => ['required', new Enum(KingdomAllianceContactChannel::class)],
            'handle' => ['required', 'string', 'max:255'],
            'last_verified_at' => ['nullable', 'date', 'before_or_equal:now'],
            'manager_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        return $validated;
    }

    /** @param array<string,PlayerReference> $players @return array<string,mixed> */
    private function contactRow(KingdomAllianceDiplomacyContact $contact, array $players): array
    {
        $created = $contact->created_by_player_id === null ? null : ($players[(string) $contact->created_by_player_id] ?? null);
        $updated = $contact->updated_by_player_id === null ? null : ($players[(string) $contact->updated_by_player_id] ?? null);
        $deactivated = $contact->deactivated_by_player_id === null ? null : ($players[(string) $contact->deactivated_by_player_id] ?? null);

        return [
            'id' => (string) $contact->id,
            'displayName' => $contact->display_name,
            'gameRole' => $contact->game_role,
            'channelType' => $contact->channel_type->value,
            'handle' => $contact->handle,
            'state' => $contact->state->value,
            'lastVerifiedAt' => $contact->last_verified_at?->toIso8601String(),
            'managerNotes' => $contact->manager_notes,
            'createdByName' => $created?->currentName,
            'updatedByName' => $updated?->currentName,
            'deactivatedByName' => $deactivated?->currentName,
            'createdAt' => $contact->created_at->toIso8601String(),
            'updatedAt' => $contact->updated_at->toIso8601String(),
            'deactivatedAt' => $contact->deactivated_at?->toIso8601String(),
        ];
    }
}

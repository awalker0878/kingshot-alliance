<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Http\Controllers;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Alliances\Services\AllianceContext;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Actions\DeactivateKingdomAllianceDiplomacyContact;
use App\Domain\Kingdoms\Actions\SaveKingdomAllianceDiplomacyContact;
use App\Domain\Kingdoms\Enums\KingdomAllianceContactChannel;
use App\Domain\Kingdoms\Models\KingdomAllianceDiplomacyContact;
use App\Domain\Kingdoms\Models\TrackedKingdomAlliance;
use App\Domain\Kingdoms\Queries\KingdomAllianceDiplomacyContactQuery;
use App\Domain\Platform\Http\Controllers\Controller;
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
        AllianceAuthorization $authorization,
        KingdomAllianceDiplomacyContactQuery $contacts,
        string $tracking,
    ): Response {
        $user = $this->user($request);
        $alliance = $context->alliance()->load('kingdom');
        if (! $authorization->allows($user, $alliance, PermissionKey::KingdomManage)) {
            throw new AuthorizationException;
        }

        $tracked = $contacts->tracking($alliance, $tracking);

        return Inertia::render('Alliance/KingdomAllianceDiplomacyContacts', [
            'alliance' => $this->allianceSummary($alliance),
            'tracking' => $this->trackingSummary($tracked, $alliance),
            'channels' => [
                ['value' => KingdomAllianceContactChannel::InGame->value, 'label' => 'In-game'],
                ['value' => KingdomAllianceContactChannel::Discord->value, 'label' => 'Discord'],
                ['value' => KingdomAllianceContactChannel::OtherHandle->value, 'label' => 'Other handle/channel'],
            ],
            'contactLimit' => KingdomAllianceDiplomacyContactQuery::CONTACT_LIMIT,
            'contacts' => $contacts->contacts($alliance, $tracking)
                ->map(fn (KingdomAllianceDiplomacyContact $contact): array => $this->contactRow($contact))
                ->values(),
        ]);
    }

    public function store(
        Request $request,
        AllianceContext $context,
        SaveKingdomAllianceDiplomacyContact $save,
        string $tracking,
    ): RedirectResponse {
        $save->handle($context->alliance(), $this->user($request), $tracking, $this->validated($request));

        return back()->with('status', 'kingdom-alliance-diplomacy-contact-saved');
    }

    public function update(
        Request $request,
        AllianceContext $context,
        SaveKingdomAllianceDiplomacyContact $save,
        string $tracking,
        string $contact,
    ): RedirectResponse {
        $save->handle($context->alliance(), $this->user($request), $tracking, $this->validated($request), $contact);

        return back()->with('status', 'kingdom-alliance-diplomacy-contact-saved');
    }

    public function deactivate(
        Request $request,
        AllianceContext $context,
        DeactivateKingdomAllianceDiplomacyContact $deactivate,
        string $tracking,
        string $contact,
    ): RedirectResponse {
        $deactivate->handle($context->alliance(), $this->user($request), $tracking, $contact);

        return back()->with('status', 'kingdom-alliance-diplomacy-contact-deactivated');
    }

    /**
     * @return array{
     *   display_name: string,
     *   game_role?: string|null,
     *   channel_type: string,
     *   handle: string,
     *   last_verified_at?: string|null,
     *   manager_notes?: string|null
     * }
     */
    private function validated(Request $request): array
    {
        /** @var array{display_name: string, game_role?: string|null, channel_type: string, handle: string, last_verified_at?: string|null, manager_notes?: string|null} $validated */
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

    /** @return array{id: string, name: string, kingdom: string|null} */
    private function allianceSummary(Alliance $alliance): array
    {
        return [
            'id' => (string) $alliance->id,
            'name' => (string) $alliance->name,
            'kingdom' => $alliance->kingdom === null ? null : (string) $alliance->kingdom->number,
        ];
    }

    /** @return array<string, mixed> */
    private function trackingSummary(TrackedKingdomAlliance $tracking, Alliance $alliance): array
    {
        return [
            'id' => (string) $tracking->id,
            'name' => (string) $tracking->kingdomAlliance->current_name,
            'tag' => $tracking->kingdomAlliance->current_tag,
            'state' => $tracking->state->value,
            'kingdom' => (string) $tracking->kingdom->number,
            'contextCurrent' => $alliance->kingdom_id !== null && $alliance->kingdom_id === $tracking->kingdom_id,
        ];
    }

    /** @return array<string, mixed> */
    private function contactRow(KingdomAllianceDiplomacyContact $contact): array
    {
        return [
            'id' => (string) $contact->id,
            'displayName' => $contact->display_name,
            'gameRole' => $contact->game_role,
            'channelType' => $contact->channel_type->value,
            'handle' => $contact->handle,
            'state' => $contact->state->value,
            'lastVerifiedAt' => $contact->last_verified_at?->toIso8601String(),
            'managerNotes' => $contact->manager_notes,
            'createdByName' => $contact->createdBy?->name,
            'updatedByName' => $contact->updatedBy?->name,
            'deactivatedByName' => $contact->deactivatedBy?->name,
            'createdAt' => $contact->created_at->toIso8601String(),
            'updatedAt' => $contact->updated_at->toIso8601String(),
            'deactivatedAt' => $contact->deactivated_at?->toIso8601String(),
        ];
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}

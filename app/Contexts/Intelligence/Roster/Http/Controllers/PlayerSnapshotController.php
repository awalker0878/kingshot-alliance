<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Roster\Http\Controllers;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceAuthorization;
use App\Contexts\Intelligence\Roster\Actions\RecordPlayerSnapshot;
use App\Contexts\Intelligence\Roster\Models\PlayerSnapshot;
use App\Contexts\Intelligence\Roster\Queries\PlayerSnapshotQuery;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class PlayerSnapshotController extends Controller
{
    public function show(
        Request $request,
        AllianceContext $context,
        AllianceIntelligenceAuthorization $intelligenceAuthorization,
        PlayerSnapshotQuery $snapshots,
        string $entry,
    ): Response {
        $user = $this->user($request);
        $alliance = $context->alliance()->load('kingdom');

        if (! $intelligenceAuthorization->allows($context->player(), $alliance, IntelligencePermission::View)) {
            throw new AuthorizationException;
        }

        $rosterEntry = AllianceRosterEntry::query()
            ->where('alliance_id', $alliance->id)
            ->with('player')
            ->findOrFail($entry);
        $membership = AllianceMembership::query()
            ->where('alliance_id', $alliance->id)
            ->where('player_id', $rosterEntry->player_id)
            ->where('status', MembershipStatus::Active->value)
            ->first();
        $canManage = $intelligenceAuthorization->allows($context->player(), $alliance, IntelligencePermission::KingdomManage);
        $latest = $snapshots->latestForEntry($alliance, $rosterEntry);

        return Inertia::render('Alliance/RosterHistory', [
            'user' => [
                'name' => (string) $user->name,
                'email' => (string) $user->email,
            ],
            'alliance' => [
                'id' => (string) $alliance->id,
                'name' => (string) $alliance->name,
                'kingdom' => $alliance->kingdom === null ? null : (string) $alliance->kingdom->number,
            ],
            'entry' => [
                'id' => (string) $rosterEntry->id,
                'gamePlayerId' => $rosterEntry->player->game_player_id,
                'name' => (string) $rosterEntry->observed_name,
                'gameRole' => $rosterEntry->game_role,
                'state' => $rosterEntry->state->value,
                'membership' => $membership === null
                    ? null
                    : ['name' => (string) $rosterEntry->player->current_name],
            ],
            'canManage' => $canManage,
            'latest' => $latest === null ? null : $this->snapshot($latest, $canManage),
            'snapshots' => $snapshots->historyForEntry($alliance, $rosterEntry)
                ->map(fn (PlayerSnapshot $snapshot): array => $this->snapshot($snapshot, $canManage))
                ->values()
                ->all(),
            'staleAfterDays' => PlayerSnapshotQuery::STALE_AFTER_DAYS,
        ]);
    }

    public function store(
        Request $request,
        AllianceContext $context,
        RecordPlayerSnapshot $record,
        string $entry,
    ): RedirectResponse {
        /** @var array{
         *   observed_name: string,
         *   power: string,
         *   progression_level?: string|null,
         *   observed_alliance_tag?: string|null,
         *   captured_at: string
         * } $validated
         */
        $validated = $request->validate([
            'observed_name' => ['required', 'string', 'max:160'],
            'power' => ['required', 'string', 'regex:/^\d{1,19}$/'],
            'progression_level' => ['nullable', 'string', 'max:64'],
            'observed_alliance_tag' => ['nullable', 'string', 'max:32'],
            'captured_at' => ['required', 'date'],
        ]);

        $record->handle($context->alliance(), $context->player(), $entry, $validated);

        return back()->with('status', 'player-snapshot-recorded');
    }

    /** @return array<string, mixed> */
    private function snapshot(PlayerSnapshot $snapshot, bool $includeActor): array
    {
        $row = [
            'id' => (string) $snapshot->id,
            'observedName' => (string) $snapshot->observed_name,
            'power' => (string) $snapshot->power,
            'progressionLevel' => $snapshot->progression_level,
            'observedAllianceTag' => $snapshot->observed_alliance_tag,
            'capturedAt' => $snapshot->captured_at->toIso8601String(),
            'source' => (string) $snapshot->source,
        ];

        if ($includeActor) {
            $row['actorName'] = $snapshot->actor?->current_name;
            $row['sourceSubscriptionId'] = $snapshot->source_subscription_id;
            $row['sourceBatchId'] = $snapshot->source_batch_id;
            $row['sourceAdapterKey'] = $snapshot->source_adapter_key;
            $row['sourceAdapterVersion'] = $snapshot->source_adapter_version;
            $row['sourceRecordId'] = $snapshot->source_record_id;
            $row['sourceIdentityHash'] = $snapshot->source_identity_hash;
            $row['sourcePayloadHash'] = $snapshot->source_payload_hash;
        }

        return $row;
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Http\Controllers;

use App\Domain\Alliances\Services\AllianceContext;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Actions\RecordPlayerSnapshot;
use App\Domain\Kingdoms\Models\AllianceRosterEntry;
use App\Domain\Kingdoms\Models\PlayerSnapshot;
use App\Domain\Kingdoms\Queries\PlayerSnapshotQuery;
use App\Domain\Platform\Http\Controllers\Controller;
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
        AllianceAuthorization $authorization,
        PlayerSnapshotQuery $snapshots,
        string $entry,
    ): Response {
        $user = $this->user($request);
        $alliance = $context->alliance()->load('kingdom');

        if (! $authorization->allows($user, $alliance, PermissionKey::AllianceView)) {
            throw new AuthorizationException;
        }

        $rosterEntry = AllianceRosterEntry::query()
            ->where('alliance_id', $alliance->id)
            ->with(['player', 'membership.user'])
            ->findOrFail($entry);
        $canManage = $authorization->allows($user, $alliance, PermissionKey::KingdomManage);
        $latest = $snapshots->latestForEntry($alliance, $rosterEntry);

        return Inertia::render('Alliance/RosterHistory', [
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
                'membership' => $rosterEntry->membership === null
                    ? null
                    : ['name' => (string) $rosterEntry->membership->user?->name],
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

        $record->handle($context->alliance(), $this->user($request), $entry, $validated);

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
            $row['actorName'] = $snapshot->actor?->name;
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

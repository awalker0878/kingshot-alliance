<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Http\Controllers;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Alliances\Services\AllianceContext;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Actions\InvalidateKingdomAllianceObservation;
use App\Domain\Kingdoms\Actions\RecordKingdomAllianceObservation;
use App\Domain\Kingdoms\Models\KingdomAllianceObservation;
use App\Domain\Kingdoms\Models\TrackedKingdomAlliance;
use App\Domain\Kingdoms\Queries\KingdomAllianceObservationQuery;
use App\Domain\Platform\Http\Controllers\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class KingdomAllianceObservationController extends Controller
{
    public function show(
        Request $request,
        AllianceContext $context,
        AllianceAuthorization $authorization,
        KingdomAllianceObservationQuery $observations,
        string $tracking,
    ): Response {
        $user = $this->user($request);
        $alliance = $context->alliance()->load('kingdom');
        if (! $authorization->allows($user, $alliance, PermissionKey::AllianceView)) {
            throw new AuthorizationException;
        }

        $canManage = $authorization->allows($user, $alliance, PermissionKey::KingdomManage);
        $tracked = $observations->tracking($alliance, $tracking);
        $latest = $observations->latestAccepted($alliance, $tracking);
        $history = $observations->history($alliance, $tracking, $canManage);

        return Inertia::render('Alliance/KingdomAllianceHistory', [
            'alliance' => $this->allianceSummary($alliance),
            'canManage' => $canManage,
            'tracking' => $this->trackingSummary($tracked, $alliance, $canManage),
            'freshness' => $observations->freshness($latest),
            'freshDays' => KingdomAllianceObservationQuery::FRESH_DAYS,
            'latest' => $latest === null ? null : $this->observationRow($latest, false),
            'history' => $history->map(fn (KingdomAllianceObservation $observation): array => $this->observationRow($observation, $canManage))->values(),
        ]);
    }

    public function store(
        Request $request,
        AllianceContext $context,
        RecordKingdomAllianceObservation $record,
        string $tracking,
    ): RedirectResponse {
        $record->handle($context->alliance(), $this->user($request), $tracking, $this->validatedObservation($request));

        return back()->with('status', 'kingdom-alliance-observation-recorded');
    }

    public function invalidate(
        Request $request,
        AllianceContext $context,
        InvalidateKingdomAllianceObservation $invalidate,
        string $tracking,
        string $observation,
    ): RedirectResponse {
        /** @var array{reason: string} $validated */
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:5000'],
        ]);
        $invalidate->handle($context->alliance(), $this->user($request), $tracking, $observation, $validated['reason']);

        return back()->with('status', 'kingdom-alliance-observation-invalidated');
    }

    /**
     * @return array{
     *   observed_name: string,
     *   observed_tag?: string|null,
     *   power?: string|null,
     *   member_count?: int|null,
     *   captured_at: string,
     *   corrects_observation_id?: string|null,
     *   correction_reason?: string|null
     * }
     */
    private function validatedObservation(Request $request): array
    {
        /** @var array{observed_name: string, observed_tag?: string|null, power?: string|null, member_count?: int|null, captured_at: string, corrects_observation_id?: string|null, correction_reason?: string|null} $validated */
        $validated = $request->validate([
            'observed_name' => ['required', 'string', 'max:160'],
            'observed_tag' => ['nullable', 'string', 'max:32'],
            'power' => ['nullable', 'regex:/^\\d+$/', 'max:19'],
            'member_count' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'captured_at' => ['required', 'date'],
            'corrects_observation_id' => ['nullable', 'string', 'max:26'],
            'correction_reason' => ['nullable', 'string', 'max:5000'],
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
    private function trackingSummary(TrackedKingdomAlliance $tracking, Alliance $alliance, bool $includePrivate): array
    {
        $row = [
            'name' => (string) $tracking->kingdomAlliance->current_name,
            'tag' => $tracking->kingdomAlliance->current_tag,
            'state' => $tracking->state->value,
            'kingdom' => (string) $tracking->kingdom->number,
            'contextCurrent' => $alliance->kingdom_id !== null && $alliance->kingdom_id === $tracking->kingdom_id,
        ];

        if ($includePrivate) {
            $row['id'] = (string) $tracking->id;
        }

        return $row;
    }

    /** @return array<string, mixed> */
    private function observationRow(KingdomAllianceObservation $observation, bool $includePrivate): array
    {
        $row = [
            'observedName' => $observation->observed_name,
            'observedTag' => $observation->observed_tag,
            'power' => $observation->power === null ? null : (string) $observation->power,
            'memberCount' => $observation->member_count,
            'capturedAt' => $observation->captured_at->toIso8601String(),
            'source' => $observation->source,
        ];

        if ($includePrivate) {
            $row['id'] = (string) $observation->id;
            $row['actorName'] = $observation->actor?->name;
            $row['correctsObservationId'] = $observation->corrects_observation_id;
            $row['invalidatedAt'] = $observation->invalidated_at?->toIso8601String();
            $row['invalidatedByName'] = $observation->invalidatedBy?->name;
            $row['invalidationReason'] = $observation->invalidation_reason;
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

<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Integrations\Http\Controllers;

use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomReferenceQuery;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AllianceApiController extends Controller
{
    public function __construct(
        private readonly AllianceReferenceQuery $alliances,
        private readonly KingdomReferenceQuery $kingdoms,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $alliance = $this->alliance($request);
        $kingdom = $this->kingdoms->find($alliance->kingdomId);

        return response()->json([
            'data' => [
                'id' => $alliance->allianceId,
                'name' => $alliance->name,
                'slug' => $alliance->slug,
                'kingdom' => $kingdom?->number,
                'language' => $alliance->language,
                'timezone' => $alliance->timezone,
            ],
        ]);
    }

    public function events(Request $request): JsonResponse
    {
        $allianceId = $this->alliance($request)->allianceId;
        $rows = DB::table('event_occurrences')
            ->join('events', 'events.id', '=', 'event_occurrences.event_id')
            ->where('events.scope', EventScope::Alliance->value)
            ->where('events.alliance_id', $allianceId)
            ->where('event_occurrences.starts_at', '>=', now()->subDay())
            ->orderBy('event_occurrences.starts_at')
            ->limit(250)
            ->get([
                'event_occurrences.id',
                'event_occurrences.starts_at',
                'event_occurrences.ends_at',
                'event_occurrences.status',
                'events.title',
                'events.timezone',
            ]);

        return response()->json(['data' => $rows]);
    }

    public function contributions(Request $request): JsonResponse
    {
        $allianceId = $this->alliance($request)->allianceId;
        $rows = DB::table('contribution_records')
            ->join('contribution_categories', function ($join): void {
                $join->on('contribution_categories.id', '=', 'contribution_records.category_id')
                    ->on('contribution_categories.alliance_id', '=', 'contribution_records.alliance_id');
            })
            ->where('contribution_records.alliance_id', $allianceId)
            ->where('contribution_records.status', 'approved')
            ->orderByDesc('contribution_records.recorded_at')
            ->limit(250)
            ->get([
                'contribution_records.id',
                'contribution_records.player_id',
                'contribution_records.value',
                'contribution_records.period_start',
                'contribution_records.period_end',
                'contribution_records.recorded_at',
                'contribution_categories.name as category',
                'contribution_categories.unit',
                'contribution_categories.data_class',
                'contribution_categories.calculation_version',
            ]);

        return response()->json(['data' => $rows]);
    }

    private function alliance(Request $request): AllianceReference
    {
        $allianceId = $request->attributes->get('alliance_id');
        abort_unless(is_string($allianceId) && $allianceId !== '', 500, 'API tenant context is missing.');

        return $this->alliances->require($allianceId);
    }
}

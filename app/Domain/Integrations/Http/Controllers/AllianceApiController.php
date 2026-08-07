<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Http\Controllers;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Platform\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AllianceApiController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $alliance = $this->alliance($request);

        return response()->json([
            'data' => [
                'id' => (string) $alliance->id,
                'name' => (string) $alliance->name,
                'slug' => (string) $alliance->slug,
                'kingdom' => $alliance->kingdom,
                'language' => (string) $alliance->language,
                'timezone' => (string) $alliance->timezone,
            ],
        ]);
    }

    public function events(Request $request): JsonResponse
    {
        $alliance = $this->alliance($request);
        $rows = DB::table('event_occurrences')
            ->join('events', function ($join): void {
                $join->on('events.id', '=', 'event_occurrences.event_id')
                    ->on('events.alliance_id', '=', 'event_occurrences.alliance_id');
            })
            ->where('event_occurrences.alliance_id', $alliance->id)
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
        $alliance = $this->alliance($request);
        $rows = DB::table('contribution_records')
            ->join('contribution_categories', function ($join): void {
                $join->on('contribution_categories.id', '=', 'contribution_records.category_id')
                    ->on('contribution_categories.alliance_id', '=', 'contribution_records.alliance_id');
            })
            ->where('contribution_records.alliance_id', $alliance->id)
            ->where('contribution_records.status', 'approved')
            ->orderByDesc('contribution_records.recorded_at')
            ->limit(250)
            ->get([
                'contribution_records.id',
                'contribution_records.membership_id',
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

    private function alliance(Request $request): Alliance
    {
        $allianceId = $request->attributes->get('alliance_id');
        abort_unless(is_string($allianceId) && $allianceId !== '', 500, 'API tenant context is missing.');

        return Alliance::query()->findOrFail($allianceId);
    }
}

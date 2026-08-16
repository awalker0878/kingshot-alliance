<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Integrations\Http\Controllers;

use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Operations\EventCore\Enums\EventScope;
use App\Shared\Http\Controller;
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
                'kingdom' => $alliance->kingdom === null ? null : (string) $alliance->kingdom->number,
                'language' => (string) $alliance->language,
                'timezone' => (string) $alliance->timezone,
            ],
        ]);
    }

    public function events(Request $request): JsonResponse
    {
        $alliance = $this->alliance($request);
        $rows = DB::table('event_occurrences')
            ->join('events', 'events.id', '=', 'event_occurrences.event_id')
            ->where('events.scope', EventScope::Alliance->value)
            ->where('events.alliance_id', $alliance->id)
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

    private function alliance(Request $request): Alliance
    {
        $allianceId = $request->attributes->get('alliance_id');
        abort_unless(is_string($allianceId) && $allianceId !== '', 500, 'API tenant context is missing.');

        return Alliance::query()->with('kingdom')->findOrFail($allianceId);
    }
}

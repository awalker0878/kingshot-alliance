<?php

declare(strict_types=1);

namespace App\Contexts\Operations\KingPerks\Queries;

use App\Contexts\Operations\KingPerks\Enums\KingPerkPlanStatus;
use App\Contexts\Operations\KingPerks\Enums\KingPerkReminderKind;
use App\Contexts\Operations\KingPerks\Enums\KingSkill;
use App\Contexts\Operations\KingPerks\ValueObjects\DueKingPerkReminder;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

final class DueKingPerkReminderQuery
{
    /** @return list<DueKingPerkReminder> */
    public function next(CarbonImmutable $now, int $limit): array
    {
        $branches = array_map(fn (KingPerkReminderKind $kind): Builder => $this->sources($kind, $now), KingPerkReminderKind::cases());
        $union = array_shift($branches);
        foreach ($branches as $branch) {
            $union->unionAll($branch);
        }

        return array_values(DB::query()->fromSub($union, 'due_reminders')
            ->orderByRaw('CASE WHEN visited_at IS NULL THEN 0 ELSE 1 END')
            ->orderBy('visited_at')->orderBy('source_id')->orderBy('kind')
            ->limit(max(1, min(50, $limit)))
            ->get()->map(static fn (object $row): DueKingPerkReminder => new DueKingPerkReminder(
                (string) $row->source_id, (string) $row->plan_id, (string) $row->kingdom_id,
                KingPerkReminderKind::from((string) $row->kind),
                $row->assigned_player_id === null ? null : (string) $row->assigned_player_id,
            ))->all());
    }

    private function sources(KingPerkReminderKind $kind, CarbonImmutable $now): Builder
    {
        $appointments = $kind->isAppointment();
        $table = $appointments ? 'king_perk_appointments' : 'king_skill_plans';
        $time = $appointments ? 'source.starts_at' : 'source.planned_activation_at';
        $query = DB::table($table.' as source')
            ->join('king_perk_plans as plan', 'source.plan_id', '=', 'plan.id')
            ->where('plan.status', '!=', KingPerkPlanStatus::Closed->value)
            ->whereIn('source.status', $kind->sourceStatuses())
            ->where($time, '>', $now)
            ->leftJoin('king_perk_reminder_cursors as progress', static function (JoinClause $join) use ($kind): void {
                $join->on('progress.source_id', '=', 'source.id')->where('progress.kind', '=', $kind->value);
            })
            ->select(['source.id as source_id', 'source.plan_id', 'plan.kingdom_id', 'progress.visited_at'])
            ->selectRaw('? AS kind', [$kind->value])
            ->selectRaw($appointments ? 'source.assigned_player_id' : 'NULL AS assigned_player_id');

        if ($kind === KingPerkReminderKind::SkillSchedulingAvailable) {
            $query->where(static function (Builder $query) use ($kind, $now, $time): void {
                foreach (KingSkill::cases() as $skill) {
                    $query->orWhere(static fn (Builder $q) => $q->where('source.skill_key', $skill->value)
                        ->where($time, '<=', $now->addMinutes($kind->leadMinutes($skill))));
                }
            });
        } else {
            $query->where($time, '<=', $now->addMinutes($kind->leadMinutes()));
        }

        return $query;
    }
}

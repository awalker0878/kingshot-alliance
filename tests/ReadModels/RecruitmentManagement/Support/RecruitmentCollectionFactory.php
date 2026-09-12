<?php

declare(strict_types=1);

namespace Tests\ReadModels\RecruitmentManagement\Support;

use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCandidate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class RecruitmentCollectionFactory
{
    /** @return array{tags:list<string>,reviewers:list<string>,members:list<string>,roster:list<string>,templates:list<string>} */
    public function seed(AllianceReference $alliance, string $actorId, RecruitmentCandidate $candidate, int $count = 55): array
    {
        $ids = ['tags' => [], 'reviewers' => [], 'members' => [], 'roster' => [], 'templates' => []];
        $at = now();
        for ($index = 0; $index < $count; $index++) {
            $name = sprintf('Collection choice %03d', $index);
            $tagId = strtolower((string) Str::ulid());
            DB::table('recruitment_tags')->insert(['id' => $tagId, 'alliance_id' => $alliance->allianceId, 'name' => $name, 'created_at' => $at, 'updated_at' => $at]);
            DB::table('recruitment_candidate_tags')->insert(['alliance_id' => $alliance->allianceId, 'candidate_id' => $candidate->id, 'tag_id' => $tagId, 'created_at' => $at, 'updated_at' => $at]);
            $ids['tags'][] = $tagId;
            foreach (['members', 'roster'] as $kind) {
                $playerId = strtolower((string) Str::ulid());
                DB::table('players')->insert(['id' => $playerId, 'current_kingdom_id' => $alliance->kingdomId, 'current_name' => $name, 'created_at' => $at, 'updated_at' => $at]);
                $base = ['id' => strtolower((string) Str::ulid()), 'alliance_id' => $alliance->allianceId, 'player_id' => $playerId, 'created_at' => $at, 'updated_at' => $at];
                if ($kind === 'members') {
                    // Retained reviewer assignments can exceed current active membership.
                    DB::table('alliance_memberships')->insert($base + ['rank' => 'r3', 'status' => $index < 55 ? 'active' : 'left', 'joined_at' => $at]);
                    DB::table('recruitment_candidate_reviewers')->insert(['id' => strtolower((string) Str::ulid()), 'alliance_id' => $alliance->allianceId, 'candidate_id' => $candidate->id, 'reviewer_player_id' => $playerId, 'assigned_by_player_id' => $actorId, 'created_at' => $at, 'updated_at' => $at]);
                    $ids['reviewers'][] = $playerId;
                } else {
                    DB::table('alliance_roster_entries')->insert($base + ['observed_name' => 'Earlier '.$name, 'state' => 'active']);
                }
                $ids[$kind][] = $playerId;
            }
            $templateId = strtolower((string) Str::ulid());
            DB::table('recruitment_decision_templates')->insert([
                'id' => $templateId, 'alliance_id' => $alliance->allianceId, 'name' => $name,
                'decision_stage' => $candidate->recruitmentStage()->value, 'subject' => 'Collection decision', 'body' => 'Complete decision body.',
                'is_active' => true, 'created_by_player_id' => $actorId, 'updated_by_player_id' => $actorId, 'created_at' => $at, 'updated_at' => $at,
            ]);
            $ids['templates'][] = $templateId;
        }

        return $ids;
    }
}

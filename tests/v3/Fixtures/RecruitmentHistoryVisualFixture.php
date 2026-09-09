<?php

declare(strict_types=1);

namespace Tests\v3\Fixtures;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentStage;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCandidate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\v3\Support\ScenarioFactory;

final class RecruitmentHistoryVisualFixture
{
    public static function seed(): void
    {
        $factory = app(ScenarioFactory::class);
        $user = User::factory()->create(['name' => 'Recruitment History Visual', 'email' => 'recruitment-history-visual@example.test']);
        $owner = $factory->player((int) $user->id, 59354);
        $alliance = $factory->alliance($owner);
        $at = now()->subDay()->format('Y-m-d H:i:s');
        foreach (['desktop', 'mobile'] as $project) {
            $candidate = RecruitmentCandidate::query()->create([
                'alliance_id' => $alliance->allianceId, 'full_name' => 'History candidate '.$project,
                'email' => 'history-'.$project.'@example.test', 'stage' => RecruitmentStage::New, 'submitted_at' => now(),
            ]);
            for ($index = 0; $index < 35; $index++) {
                $base = ['alliance_id' => $alliance->allianceId, 'candidate_id' => $candidate->id, 'created_at' => $at, 'updated_at' => $at];
                DB::table('recruitment_notes')->insert($base + ['id' => (string) Str::ulid(), 'author_player_id' => $owner->playerId, 'body' => 'Historical note '.$index]);
                DB::table('recruitment_stage_history')->insert($base + ['id' => (string) Str::ulid(), 'from_stage' => 'new', 'to_stage' => 'screening', 'changed_at' => $at]);
                $communicationId = (string) Str::ulid();
                DB::table('recruitment_communications')->insert($base + ['id' => $communicationId, 'subject' => 'Historical decision '.$index, 'body' => 'Decision body '.$index, 'status' => 'prepared', 'idempotency_key' => hash('sha256', $communicationId), 'created_by_player_id' => $owner->playerId]);
                DB::table('recruitment_candidates')->insert([
                    'id' => (string) Str::ulid(), 'alliance_id' => $alliance->allianceId, 'full_name' => 'Earlier '.$project.' candidate '.$index,
                    'email' => $candidate->email, 'stage' => 'declined', 'submitted_at' => $at, 'created_at' => $at, 'updated_at' => $at,
                ]);
            }
        }
    }
}

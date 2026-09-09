<?php

declare(strict_types=1);

namespace Tests\v3\Fixtures;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Alliance\Lifecycle\Actions\CreateAlliance;
use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentStage;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCandidate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\v3\Support\RecruitmentCollectionFactory;
use Tests\v3\Support\ScenarioFactory;

final class RecruitmentHistoryVisualFixture
{
    public static function seed(): void
    {
        $factory = app(ScenarioFactory::class);
        $user = User::factory()->create(['name' => 'Recruitment History Visual', 'email' => 'recruitment-history-visual@example.test']);
        $owner = $factory->player((int) $user->id, 59354, 'visual-recruitment-history-owner');
        $allianceId = app(CreateAlliance::class)->handle((int) $user->id, $owner->playerId, 'Recruitment History Alliance', 'recruitment-history-visual', 'en', 'UTC');
        $at = now()->subDay()->format('Y-m-d H:i:s');
        $factory->roster($owner, app(AllianceReferenceQuery::class)->require($allianceId));
        $unrelated = $factory->unclaimedPlayer(59354, 'visual-member-history-unrelated');
        $events = [];
        foreach ([[$owner->playerId, 55, $at], [$unrelated->playerId, 501, now()->format('Y-m-d H:i:s')]] as [$targetId, $count, $createdAt]) {
            for ($index = 0; $index < $count; $index++) {
                $events[] = [
                    'id' => (string) Str::ulid(), 'alliance_id' => $allianceId, 'actor_player_id' => $owner->playerId,
                    'event' => 'membership.rank_changed', 'subject_type' => Alliance::class, 'subject_id' => $allianceId,
                    'metadata' => json_encode(['target_player_id' => $targetId, 'new_rank' => 'r3'], JSON_THROW_ON_ERROR),
                    'created_at' => $createdAt,
                ];
            }
        }
        DB::table('audit_events')->insert($events);
        foreach (['desktop', 'mobile'] as $project) {
            $candidate = RecruitmentCandidate::query()->create([
                'alliance_id' => $allianceId, 'full_name' => 'History candidate '.$project,
                'email' => 'history-'.$project.'@example.test', 'stage' => RecruitmentStage::New, 'submitted_at' => now(),
            ]);
            for ($index = 0; $index < 35; $index++) {
                $base = ['alliance_id' => $allianceId, 'candidate_id' => $candidate->id, 'created_at' => $at, 'updated_at' => $at];
                DB::table('recruitment_notes')->insert($base + ['id' => (string) Str::ulid(), 'author_player_id' => $owner->playerId, 'body' => 'Historical note '.$index]);
                DB::table('recruitment_stage_history')->insert($base + ['id' => (string) Str::ulid(), 'from_stage' => 'new', 'to_stage' => 'screening', 'changed_at' => $at]);
                $communicationId = (string) Str::ulid();
                DB::table('recruitment_communications')->insert($base + ['id' => $communicationId, 'subject' => 'Historical decision '.$index, 'body' => 'Decision body '.$index, 'status' => 'prepared', 'idempotency_key' => hash('sha256', $communicationId), 'created_by_player_id' => $owner->playerId]);
                DB::table('recruitment_candidates')->insert([
                    'id' => (string) Str::ulid(), 'alliance_id' => $allianceId, 'full_name' => 'Earlier '.$project.' candidate '.$index,
                    'email' => $candidate->email, 'stage' => 'declined', 'submitted_at' => $at, 'created_at' => $at, 'updated_at' => $at,
                ]);
            }
        }
        self::seedCatalogues();
        self::seedCollections();
    }

    private static function seedCollections(): void
    {
        $factory = app(ScenarioFactory::class);
        foreach (['desktop', 'mobile'] as $project) {
            $user = User::factory()->create(['name' => 'Recruitment Collections Visual', 'email' => 'recruitment-collections-'.$project.'@example.test']);
            $owner = $factory->player((int) $user->id, 59356, 'visual-recruitment-collections-'.$project);
            $alliance = $factory->alliance($owner);
            $candidate = RecruitmentCandidate::query()->create([
                'alliance_id' => $alliance->allianceId, 'full_name' => 'Collection candidate '.$project,
                'email' => 'collection-'.$project.'@example.test', 'stage' => RecruitmentStage::Accepted, 'submitted_at' => now(),
            ]);
            app(RecruitmentCollectionFactory::class)->seed($alliance, $owner->playerId, $candidate);
        }
    }

    private static function seedCatalogues(): void
    {
        $factory = app(ScenarioFactory::class);
        $user = User::factory()->create(['name' => 'Recruitment Catalogue Visual', 'email' => 'recruitment-catalogue-visual@example.test']);
        $owner = $factory->player((int) $user->id, 59355, 'visual-recruitment-catalogue-owner');
        $alliance = $factory->alliance($owner);
        for ($index = 0; $index < 55; $index++) {
            $base = [
                'alliance_id' => $alliance->allianceId, 'is_active' => false,
                'created_by_player_id' => $owner->playerId, 'updated_by_player_id' => $owner->playerId,
                'created_at' => now(), 'updated_at' => now(),
            ];
            DB::table('recruitment_questions')->insert($base + [
                'id' => strtolower((string) Str::ulid()), 'prompt' => sprintf('Catalogue question %03d', $index),
                'question_type' => 'short_text', 'position' => $index, 'is_required' => false,
            ]);
            DB::table('recruitment_decision_templates')->insert($base + [
                'id' => strtolower((string) Str::ulid()), 'name' => sprintf('Catalogue template %03d', $index),
                'decision_stage' => 'accepted', 'subject' => 'Complete decision', 'body' => 'Complete retained template body.',
            ]);
            DB::table('recruitment_onboarding_items')->insert($base + [
                'id' => strtolower((string) Str::ulid()), 'name' => sprintf('Catalogue onboarding %03d', $index),
                'description' => 'Complete retained task.', 'position' => $index, 'is_required' => false,
            ]);
        }
    }
}

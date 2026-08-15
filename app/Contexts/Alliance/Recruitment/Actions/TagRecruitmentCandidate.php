<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Recruitment\Actions;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceMutationAuthority;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCandidate;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentTag;
use App\Contexts\GameWorld\Models\Player;
use App\Shared\Audit\Services\AuditRecorder;
use App\Shared\Messaging\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class TagRecruitmentCandidate
{
    public function __construct(
        private AllianceMutationAuthority $authority,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(Player $actor, Alliance $alliance, RecruitmentCandidate $candidate, string $name): RecruitmentTag
    {
        $normalizedName = Str::lower(trim($name));
        if ($normalizedName === '') {
            throw ValidationException::withMessages(['tag' => 'A recruitment tag name is required.']);
        }

        if (mb_strlen($normalizedName) > 80) {
            throw ValidationException::withMessages(['tag' => 'Recruitment tags may not exceed 80 characters.']);
        }

        return DB::transaction(function () use ($actor, $alliance, $candidate, $normalizedName): RecruitmentTag {
            $context = $this->authority->require($actor, $alliance, AlliancePermission::RecruitmentManage);

            $currentCandidate = RecruitmentCandidate::query()
                ->whereKey($candidate->id)
                ->where('alliance_id', $context->alliance->id)
                ->sharedLock()
                ->firstOrFail();

            if ($currentCandidate->merged_into_id !== null) {
                throw ValidationException::withMessages([
                    'candidate' => 'Tags must be changed on the current merged candidate record.',
                ]);
            }

            $tag = RecruitmentTag::query()->firstOrCreate([
                'alliance_id' => $context->alliance->id,
                'name' => $normalizedName,
            ]);

            // The pivot uniqueness constraint is the concurrency primitive here;
            // insertOrIgnore is an atomic compare-and-set for duplicate attachment.
            $inserted = DB::table('recruitment_candidate_tags')->insertOrIgnore([
                'alliance_id' => $context->alliance->id,
                'candidate_id' => $currentCandidate->id,
                'tag_id' => $tag->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]) === 1;

            if ($inserted) {
                $this->audit->record('recruitment.candidate.tagged', $context->actor, $currentCandidate, $context->alliance, [
                    'tag_id' => $tag->id,
                    'tag' => $tag->name,
                ]);
                $this->outbox->record('recruitment.candidate.tagged', (string) $context->alliance->id, $currentCandidate, [
                    'candidate_id' => $currentCandidate->id,
                    'tag_id' => $tag->id,
                ]);
            }

            return $tag;
        });
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Recruitment\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Platform\Services\OutboxRecorder;
use App\Domain\Recruitment\Models\RecruitmentCandidate;
use App\Domain\Recruitment\Models\RecruitmentTag;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class TagRecruitmentCandidate
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(Player $actor, Alliance $alliance, RecruitmentCandidate $candidate, string $name): RecruitmentTag
    {
        if (! $this->authorization->allows($actor, $alliance, PermissionKey::RecruitmentManage)) {
            throw new AuthorizationException('You are not allowed to tag recruitment candidates.');
        }

        if ($candidate->alliance_id !== $alliance->id) {
            throw new AuthorizationException('The candidate belongs to another alliance.');
        }

        $normalizedName = Str::lower(trim($name));
        if ($normalizedName === '') {
            throw ValidationException::withMessages(['tag' => 'A recruitment tag name is required.']);
        }

        if (mb_strlen($normalizedName) > 80) {
            throw ValidationException::withMessages(['tag' => 'Recruitment tags may not exceed 80 characters.']);
        }

        return DB::transaction(function () use ($actor, $alliance, $candidate, $normalizedName): RecruitmentTag {
            $tag = RecruitmentTag::query()->firstOrCreate([
                'alliance_id' => $alliance->id,
                'name' => $normalizedName,
            ]);

            $attached = ! DB::table('recruitment_candidate_tags')
                ->where('candidate_id', $candidate->id)
                ->where('tag_id', $tag->id)
                ->lockForUpdate()
                ->exists();

            if ($attached) {
                DB::table('recruitment_candidate_tags')->insert([
                    'alliance_id' => $alliance->id,
                    'candidate_id' => $candidate->id,
                    'tag_id' => $tag->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $this->audit->record('recruitment.candidate.tagged', $actor, $candidate, $alliance, [
                    'tag_id' => $tag->id,
                    'tag' => $tag->name,
                ]);
                $this->outbox->record('recruitment.candidate.tagged', (string) $alliance->id, $candidate, [
                    'candidate_id' => $candidate->id,
                    'tag_id' => $tag->id,
                ]);
            }

            return $tag;
        });
    }
}

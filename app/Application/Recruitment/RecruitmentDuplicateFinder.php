<?php

declare(strict_types=1);

namespace App\Application\Recruitment;

use App\Models\Alliance;
use App\Models\RecruitmentCandidate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

final class RecruitmentDuplicateFinder
{
    /** @return Collection<int, RecruitmentCandidate> */
    public function forCandidate(Alliance $alliance, RecruitmentCandidate $candidate): Collection
    {
        if ($candidate->alliance_id !== $alliance->id) {
            return new Collection;
        }

        $email = Str::lower(trim((string) $candidate->email));
        $contactHandle = $candidate->contact_handle === null
            ? null
            : Str::lower(trim((string) $candidate->contact_handle));

        return RecruitmentCandidate::query()
            ->where('alliance_id', $alliance->id)
            ->whereKeyNot($candidate->id)
            ->whereNull('merged_into_id')
            ->where(function ($query) use ($email, $contactHandle): void {
                $query->whereRaw('LOWER(email) = ?', [$email]);

                if ($contactHandle !== null && $contactHandle !== '') {
                    $query->orWhereRaw('LOWER(contact_handle) = ?', [$contactHandle]);
                }
            })
            ->orderBy('submitted_at')
            ->get();
    }
}

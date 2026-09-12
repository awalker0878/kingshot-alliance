<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Recruitment\Queries;

use App\Contexts\Alliance\Recruitment\Models\RecruitmentCandidate;
use App\Shared\Infrastructure\Pagination\PageSlice;
use App\Shared\Infrastructure\Pagination\ScopedCursorCodec;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class RecruitmentDuplicateFinder
{
    public const PAGE_SIZE = 25;

    public function __construct(private ScopedCursorCodec $cursors) {}

    /** @return PageSlice<RecruitmentCandidate> */
    public function forCandidate(string $allianceId, RecruitmentCandidate $candidate, ?string $cursor = null): PageSlice
    {
        if ((string) $candidate->alliance_id !== $allianceId || $candidate->anonymized_at !== null) {
            return new PageSlice([], null, self::PAGE_SIZE);
        }

        $email = Str::lower(trim((string) $candidate->email));
        $contactHandle = $candidate->contact_handle === null
            ? null
            : Str::lower(trim((string) $candidate->contact_handle));
        $scope = 'recruitment-duplicates|'.$allianceId.'|'.$candidate->id.'|'.hash('sha256', json_encode([$email, $contactHandle], JSON_THROW_ON_ERROR));
        $query = RecruitmentCandidate::query()
            ->where('alliance_id', $allianceId)
            ->where('id', '!=', $candidate->id)
            ->whereNull('merged_into_id')
            ->whereNull('anonymized_at')
            ->where(function (Builder $query) use ($email, $contactHandle): void {
                $query->whereRaw('LOWER(email) = ?', [$email]);

                if ($contactHandle !== null && $contactHandle !== '') {
                    $query->orWhereRaw('LOWER(contact_handle) = ?', [$contactHandle]);
                }
            });
        if ($cursor !== null && $cursor !== '') {
            $position = $this->cursors->decode($cursor, $scope);
            $submittedAt = $position['submitted_at'] ?? null;
            $id = $position['id'] ?? null;
            if (! is_string($submittedAt) || ! is_string($id)) {
                throw ValidationException::withMessages(['cursor' => 'The duplicate candidate cursor is incomplete.']);
            }
            $query->where(static function (Builder $row) use ($submittedAt, $id): void {
                $row->where('submitted_at', '>', $submittedAt)->orWhere(static function (Builder $tie) use ($submittedAt, $id): void {
                    $tie->where('submitted_at', $submittedAt)->where('id', '>', $id);
                });
            });
        }
        $rows = $query->orderBy('submitted_at')->orderBy('id')->limit(self::PAGE_SIZE + 1)->get();
        $items = $rows->take(self::PAGE_SIZE)->values();
        $last = $items->last();
        $nextCursor = $rows->count() > self::PAGE_SIZE && $last instanceof RecruitmentCandidate
            ? $this->cursors->encode($scope, ['submitted_at' => (string) $last->getRawOriginal('submitted_at'), 'id' => (string) $last->id])
            : null;

        return new PageSlice(array_values($items->all()), $nextCursor, self::PAGE_SIZE, $cursor === null || $cursor === '');
    }
}

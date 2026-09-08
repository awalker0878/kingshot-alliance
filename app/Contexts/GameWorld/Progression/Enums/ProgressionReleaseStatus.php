<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Progression\Enums;

enum ProgressionReleaseStatus: string
{
    case Candidate = 'candidate';
    case Reviewed = 'reviewed';
    case Published = 'published';
    case Superseded = 'superseded';
    case Rejected = 'rejected';

    /** @param array<string,mixed> $release */
    public static function fromRelease(array $release): self
    {
        $value = $release['release_status'] ?? null;
        if (is_string($value) && ($status = self::tryFrom($value)) instanceof self) {
            return $status;
        }

        // Checked-in schema-v1/v2 releases are runtime releases. New release builders
        // should emit release_status explicitly; absence on the historical manifests
        // therefore means published rather than candidate.
        return self::Published;
    }
}

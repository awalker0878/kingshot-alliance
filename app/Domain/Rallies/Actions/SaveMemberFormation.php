<?php

declare(strict_types=1);

namespace App\Domain\Rallies\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Events\Services\EventOutbox;
use App\Domain\Identity\Models\User;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use App\Domain\Rallies\Models\MemberFormation;
use App\Domain\Rallies\ValueObjects\FormationComposition;
use DomainException;
use Illuminate\Support\Facades\DB;

final class SaveMemberFormation
{
    public function __construct(
        private AuditRecorder $audit,
        private EventOutbox $outbox,
    ) {}

    /** @param list<string> $heroes */
    public function handle(
        User $actor,
        Alliance $alliance,
        string $name,
        FormationComposition $composition,
        array $heroes = [],
        ?string $notes = null,
        bool $isDefault = false,
    ): MemberFormation {
        $membership = AllianceMembership::query()
            ->where('alliance_id', $alliance->id)
            ->where('user_id', $actor->id)
            ->where('status', MembershipStatus::Active->value)
            ->first();

        if (! $membership instanceof AllianceMembership) {
            throw new DomainException('An active alliance membership is required to save a formation.');
        }

        $heroList = array_values(array_filter(array_map(
            static fn (string $hero): string => trim($hero),
            $heroes,
        ), static fn (string $hero): bool => $hero !== ''));

        return DB::transaction(function () use (
            $actor,
            $alliance,
            $membership,
            $name,
            $composition,
            $heroList,
            $notes,
            $isDefault,
        ): MemberFormation {
            if ($isDefault) {
                MemberFormation::query()
                    ->where('membership_id', $membership->id)
                    ->where('alliance_id', $alliance->id)
                    ->update(['is_default' => false]);
            }

            $formation = MemberFormation::query()->updateOrCreate(
                [
                    'membership_id' => $membership->id,
                    'name' => trim($name),
                ],
                [
                    'alliance_id' => $alliance->id,
                    'heroes' => $heroList === [] ? null : $heroList,
                    ...$composition->toArray(),
                    'notes' => $notes === null ? null : trim($notes),
                    'is_default' => $isDefault,
                ],
            );

            $this->audit->record('formation.saved', $actor, $formation, $alliance, [
                'is_default' => $isDefault,
            ]);
            $this->outbox->record('formation.saved', $alliance, $formation, [
                'membership_id' => $membership->id,
            ]);

            return $formation;
        });
    }
}

<?php

declare(strict_types=1);

namespace App\Application\Identity;

use App\Models\Alliance;
use App\Models\AllianceMembership;
use App\Models\User;
use LogicException;

final class AllianceContext
{
    private ?Alliance $alliance = null;

    private ?AllianceMembership $membership = null;

    public function __construct(private readonly AllianceAuthorization $authorization) {}

    public function activate(Alliance $alliance, User $user): void
    {
        $membership = $this->authorization->activeMembership($user, $alliance);

        if ($membership === null) {
            throw new LogicException('Active alliance membership is required.');
        }

        $this->alliance = $alliance;
        $this->membership = $membership;
    }

    public function alliance(): Alliance
    {
        return $this->alliance ?? throw new LogicException('Alliance context has not been resolved.');
    }

    public function membership(): AllianceMembership
    {
        return $this->membership ?? throw new LogicException('Alliance membership context has not been resolved.');
    }

    public function clear(): void
    {
        $this->alliance = null;
        $this->membership = null;
    }
}

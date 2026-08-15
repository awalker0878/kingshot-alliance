<?php

declare(strict_types=1);

namespace App\Shared\Audit\Contracts;

interface AuditActor
{
    public function auditUserId(): ?int;

    public function auditPlayerId(): ?string;
}

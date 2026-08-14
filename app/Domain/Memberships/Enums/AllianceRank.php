<?php

declare(strict_types=1);

namespace App\Domain\Memberships\Enums;

enum AllianceRank: string
{
    case R1 = 'r1';
    case R2 = 'r2';
    case R3 = 'r3';
    case R4 = 'r4';
    case R5 = 'r5';

    public function label(): string
    {
        return strtoupper($this->value);
    }

    public function level(): int
    {
        return match ($this) {
            self::R1 => 100,
            self::R2 => 200,
            self::R3 => 300,
            self::R4 => 400,
            self::R5 => 500,
        };
    }

    public function isLeader(): bool
    {
        return $this === self::R5;
    }

    public function isOfficer(): bool
    {
        return $this === self::R4;
    }
}

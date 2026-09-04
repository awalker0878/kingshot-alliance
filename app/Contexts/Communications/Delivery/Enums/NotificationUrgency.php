<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\Enums;

enum NotificationUrgency: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';

    public function atLeast(self $other): bool
    {
        return $this->weight() >= $other->weight();
    }

    private function weight(): int
    {
        return match ($this) {
            self::Low => 0,
            self::Normal => 1,
            self::High => 2,
            self::Urgent => 3,
        };
    }
}

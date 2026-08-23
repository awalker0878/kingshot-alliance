<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Enums;

enum NoticeReaction: string
{
    case Like = 'like';
    case Dislike = 'dislike';
}

<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Recruitment\Enums;

enum RecruitmentQuestionType: string
{
    case ShortText = 'short_text';
    case LongText = 'long_text';
    case Select = 'select';
    case MultiSelect = 'multi_select';
    case Checkbox = 'checkbox';
}

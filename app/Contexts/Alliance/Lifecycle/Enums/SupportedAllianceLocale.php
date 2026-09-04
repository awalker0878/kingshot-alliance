<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Lifecycle\Enums;

enum SupportedAllianceLocale: string
{
    case English = 'en';
    case Arabic = 'ar';
    case German = 'de';
    case Spanish = 'es';
    case French = 'fr';
    case Indonesian = 'id';
    case Italian = 'it';
    case Japanese = 'ja';
    case Korean = 'ko';
    case Polish = 'pl';
    case PortugueseBrazil = 'pt-BR';
    case Russian = 'ru';
    case Thai = 'th';
    case Turkish = 'tr';
    case Vietnamese = 'vi';
    case ChineseSimplified = 'zh-CN';
    case ChineseTraditional = 'zh-TW';
}

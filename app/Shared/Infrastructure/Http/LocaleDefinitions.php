<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

// Generated from resources/data/localization/locales.json. Run npm run localization:generate; do not edit.
final class LocaleDefinitions
{
    public const DEFAULT = 'en';

    /** @var list<string> */
    public const CODES = ['en', 'ar', 'de', 'es', 'fr', 'id', 'it', 'ja', 'ko', 'pl', 'pt-BR', 'ru', 'th', 'tr', 'vi', 'zh-CN', 'zh-TW'];

    /** @var array<string, array{nativeName: string, englishName: string, direction: string}> */
    public const LOCALES = [
        'en' => ['nativeName' => 'English', 'englishName' => 'English', 'direction' => 'ltr'],
        'ar' => ['nativeName' => 'العربية', 'englishName' => 'Arabic', 'direction' => 'rtl'],
        'de' => ['nativeName' => 'Deutsch', 'englishName' => 'German', 'direction' => 'ltr'],
        'es' => ['nativeName' => 'Español', 'englishName' => 'Spanish', 'direction' => 'ltr'],
        'fr' => ['nativeName' => 'Français', 'englishName' => 'French', 'direction' => 'ltr'],
        'id' => ['nativeName' => 'Bahasa Indonesia', 'englishName' => 'Indonesian', 'direction' => 'ltr'],
        'it' => ['nativeName' => 'Italiano', 'englishName' => 'Italian', 'direction' => 'ltr'],
        'ja' => ['nativeName' => '日本語', 'englishName' => 'Japanese', 'direction' => 'ltr'],
        'ko' => ['nativeName' => '한국어', 'englishName' => 'Korean', 'direction' => 'ltr'],
        'pl' => ['nativeName' => 'Polski', 'englishName' => 'Polish', 'direction' => 'ltr'],
        'pt-BR' => ['nativeName' => 'Português (Brasil)', 'englishName' => 'Portuguese (Brazil)', 'direction' => 'ltr'],
        'ru' => ['nativeName' => 'Русский', 'englishName' => 'Russian', 'direction' => 'ltr'],
        'th' => ['nativeName' => 'ไทย', 'englishName' => 'Thai', 'direction' => 'ltr'],
        'tr' => ['nativeName' => 'Türkçe', 'englishName' => 'Turkish', 'direction' => 'ltr'],
        'vi' => ['nativeName' => 'Tiếng Việt', 'englishName' => 'Vietnamese', 'direction' => 'ltr'],
        'zh-CN' => ['nativeName' => '简体中文', 'englishName' => 'Chinese (Simplified)', 'direction' => 'ltr'],
        'zh-TW' => ['nativeName' => '繁體中文', 'englishName' => 'Chinese (Traditional)', 'direction' => 'ltr'],
    ];

    /** @var list<array{tag: string, descendants: bool, code: string}> */
    public const ALIASES = [
        ['tag' => 'en', 'descendants' => true, 'code' => 'en'],
        ['tag' => 'ar', 'descendants' => true, 'code' => 'ar'],
        ['tag' => 'de', 'descendants' => true, 'code' => 'de'],
        ['tag' => 'es', 'descendants' => true, 'code' => 'es'],
        ['tag' => 'fr', 'descendants' => true, 'code' => 'fr'],
        ['tag' => 'id', 'descendants' => true, 'code' => 'id'],
        ['tag' => 'it', 'descendants' => true, 'code' => 'it'],
        ['tag' => 'ja', 'descendants' => true, 'code' => 'ja'],
        ['tag' => 'ko', 'descendants' => true, 'code' => 'ko'],
        ['tag' => 'pl', 'descendants' => true, 'code' => 'pl'],
        ['tag' => 'pt', 'descendants' => false, 'code' => 'pt-BR'],
        ['tag' => 'pt-br', 'descendants' => true, 'code' => 'pt-BR'],
        ['tag' => 'ru', 'descendants' => true, 'code' => 'ru'],
        ['tag' => 'th', 'descendants' => true, 'code' => 'th'],
        ['tag' => 'tr', 'descendants' => true, 'code' => 'tr'],
        ['tag' => 'vi', 'descendants' => true, 'code' => 'vi'],
        ['tag' => 'zh', 'descendants' => false, 'code' => 'zh-CN'],
        ['tag' => 'zh-cn', 'descendants' => true, 'code' => 'zh-CN'],
        ['tag' => 'zh-hans', 'descendants' => true, 'code' => 'zh-CN'],
        ['tag' => 'zh-tw', 'descendants' => true, 'code' => 'zh-TW'],
        ['tag' => 'zh-hant', 'descendants' => true, 'code' => 'zh-TW'],
        ['tag' => 'zh-hk', 'descendants' => true, 'code' => 'zh-TW'],
    ];
}

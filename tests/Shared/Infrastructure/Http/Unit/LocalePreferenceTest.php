<?php

declare(strict_types=1);

namespace Tests\Shared\Infrastructure\Http\Unit;

use App\Shared\Infrastructure\Http\LocalePreference;
use PHPUnit\Framework\TestCase;

final class LocalePreferenceTest extends TestCase
{
    public function test_client_and_server_accept_the_same_versioned_preference_cookie(): void
    {
        $json = file_get_contents(dirname(__DIR__, 5).'/resources/data/localization/locale-preference-cases.json');
        self::assertNotFalse($json);
        foreach (json_decode($json, true, 512, JSON_THROW_ON_ERROR) as $case) {
            self::assertSame($case['expected'], LocalePreference::locale($case['value']), json_encode($case['value']));
        }
    }
}

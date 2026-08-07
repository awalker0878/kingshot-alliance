<?php

declare(strict_types=1);

namespace Tests\Unit\Identity;

use App\Domain\Identity\Services\TotpService;
use PHPUnit\Framework\TestCase;

final class TotpServiceTest extends TestCase
{
    public function test_sha1_rfc_6238_vector_truncated_to_six_digits(): void
    {
        $service = new TotpService;
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

        self::assertSame('287082', $service->codeForCounter($secret, 1));
        self::assertTrue($service->verify($secret, '287082', 59, 0));
        self::assertFalse($service->verify($secret, '287083', 59, 0));
    }

    public function test_generated_secret_has_at_least_128_bits_of_entropy_and_round_trips(): void
    {
        $service = new TotpService;
        $secret = $service->generateSecret();
        $counter = intdiv(time(), 30);
        $code = $service->codeForCounter($secret, $counter);

        self::assertGreaterThanOrEqual(26, strlen($secret));
        self::assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret);
        self::assertTrue($service->verify($secret, $code));
    }
}

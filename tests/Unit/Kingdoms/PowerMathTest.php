<?php

declare(strict_types=1);

namespace Tests\Unit\Kingdoms;

use App\Domain\Kingdoms\Services\PowerMath;
use PHPUnit\Framework\TestCase;

final class PowerMathTest extends TestCase
{
    private PowerMath $math;

    protected function setUp(): void
    {
        parent::setUp();
        $this->math = new PowerMath;
    }

    public function test_sum_can_exceed_signed_64_bit_without_precision_loss(): void
    {
        self::assertSame(
            '18446744073709551614',
            $this->math->sum(['9223372036854775807', '9223372036854775807']),
        );
    }

    public function test_average_rounds_to_nearest_whole_power(): void
    {
        self::assertSame('2', $this->math->averageRounded(['1', '2', '3']));
        self::assertSame('3', $this->math->averageRounded(['2', '3']));
        self::assertNull($this->math->averageRounded([]));
    }

    public function test_median_preserves_half_power_for_even_sets(): void
    {
        self::assertSame('20', $this->math->median(['30', '10', '20']));
        self::assertSame('15.5', $this->math->median(['21', '10']));
        self::assertNull($this->math->median([]));
    }

    public function test_signed_differences_and_aggregate_changes_are_exact(): void
    {
        self::assertSame('20', $this->math->difference('100', '80'));
        self::assertSame('-50', $this->math->difference('200', '250'));
        self::assertSame('0', $this->math->difference('42', '42'));
        self::assertSame('-30', $this->math->addSigned('20', '-50'));
        self::assertSame('18446744073709551614', $this->math->addSigned(
            '9223372036854775807',
            '9223372036854775807',
        ));
    }
}

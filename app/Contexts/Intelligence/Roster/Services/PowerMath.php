<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Roster\Services;

final class PowerMath
{
    /** @param list<string> $values */
    public function sum(array $values): string
    {
        $total = '0';

        foreach ($values as $value) {
            $total = $this->addUnsigned($total, $value);
        }

        return $total;
    }

    /** @param list<string> $values */
    public function averageRounded(array $values): ?string
    {
        if ($values === []) {
            return null;
        }

        [$quotient, $remainder] = $this->divideUnsignedByInt($this->sum($values), count($values));

        if (($remainder * 2) >= count($values)) {
            return $this->addUnsigned($quotient, '1');
        }

        return $quotient;
    }

    /** @param list<string> $values */
    public function median(array $values): ?string
    {
        if ($values === []) {
            return null;
        }

        usort($values, fn (string $left, string $right): int => $this->compareUnsigned($left, $right));
        $count = count($values);
        $middle = intdiv($count, 2);

        if (($count % 2) === 1) {
            return $values[$middle];
        }

        $sum = $this->addUnsigned($values[$middle - 1], $values[$middle]);
        [$quotient, $remainder] = $this->divideUnsignedByInt($sum, 2);

        return $remainder === 0 ? $quotient : $quotient.'.5';
    }

    public function difference(string $current, string $baseline): string
    {
        $comparison = $this->compareUnsigned($current, $baseline);

        if ($comparison === 0) {
            return '0';
        }

        if ($comparison > 0) {
            return $this->subtractUnsigned($current, $baseline);
        }

        return '-'.$this->subtractUnsigned($baseline, $current);
    }

    public function addSigned(string $left, string $right): string
    {
        [$leftNegative, $leftMagnitude] = $this->splitSign($left);
        [$rightNegative, $rightMagnitude] = $this->splitSign($right);

        if ($leftNegative === $rightNegative) {
            $sum = $this->addUnsigned($leftMagnitude, $rightMagnitude);

            return $sum === '0' || ! $leftNegative ? $sum : '-'.$sum;
        }

        $comparison = $this->compareUnsigned($leftMagnitude, $rightMagnitude);
        if ($comparison === 0) {
            return '0';
        }

        if ($comparison > 0) {
            $magnitude = $this->subtractUnsigned($leftMagnitude, $rightMagnitude);

            return $leftNegative ? '-'.$magnitude : $magnitude;
        }

        $magnitude = $this->subtractUnsigned($rightMagnitude, $leftMagnitude);

        return $rightNegative ? '-'.$magnitude : $magnitude;
    }

    private function addUnsigned(string $left, string $right): string
    {
        $leftIndex = strlen($left) - 1;
        $rightIndex = strlen($right) - 1;
        $carry = 0;
        $result = '';

        while ($leftIndex >= 0 || $rightIndex >= 0 || $carry > 0) {
            $sum = $carry;

            if ($leftIndex >= 0) {
                $sum += (int) $left[$leftIndex--];
            }

            if ($rightIndex >= 0) {
                $sum += (int) $right[$rightIndex--];
            }

            $result = (string) ($sum % 10).$result;
            $carry = intdiv($sum, 10);
        }

        return ltrim($result, '0') ?: '0';
    }

    private function subtractUnsigned(string $left, string $right): string
    {
        $leftIndex = strlen($left) - 1;
        $rightIndex = strlen($right) - 1;
        $borrow = 0;
        $result = '';

        while ($leftIndex >= 0) {
            $digit = (int) $left[$leftIndex--] - $borrow;
            $borrow = 0;

            if ($rightIndex >= 0) {
                $digit -= (int) $right[$rightIndex--];
            }

            if ($digit < 0) {
                $digit += 10;
                $borrow = 1;
            }

            $result = (string) $digit.$result;
        }

        return ltrim($result, '0') ?: '0';
    }

    private function compareUnsigned(string $left, string $right): int
    {
        $left = ltrim($left, '0') ?: '0';
        $right = ltrim($right, '0') ?: '0';

        if (strlen($left) !== strlen($right)) {
            return strlen($left) <=> strlen($right);
        }

        return strcmp($left, $right) <=> 0;
    }

    /** @return array{string, int} */
    private function divideUnsignedByInt(string $value, int $divisor): array
    {
        $quotient = '';
        $remainder = 0;

        foreach (str_split($value) as $digit) {
            $current = ($remainder * 10) + (int) $digit;
            $quotient .= (string) intdiv($current, $divisor);
            $remainder = $current % $divisor;
        }

        return [ltrim($quotient, '0') ?: '0', $remainder];
    }

    /** @return array{bool, string} */
    private function splitSign(string $value): array
    {
        if (str_starts_with($value, '-')) {
            return [true, substr($value, 1) ?: '0'];
        }

        return [false, $value];
    }
}

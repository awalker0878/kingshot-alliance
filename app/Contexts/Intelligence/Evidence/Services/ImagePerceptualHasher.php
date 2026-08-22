<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Services;

final class ImagePerceptualHasher
{
    public function hashFile(string $path): ?string
    {
        if (! extension_loaded('gd')) {
            return null;
        }
        $bytes = @file_get_contents($path);
        if (! is_string($bytes) || $bytes === '') {
            return null;
        }
        $source = @imagecreatefromstring($bytes);
        if ($source === false) {
            return null;
        }
        $sample = imagecreatetruecolor(9, 8);
        if ($sample === false) {
            imagedestroy($source);
            return null;
        }
        try {
            if (! imagecopyresampled($sample, $source, 0, 0, 0, 0, 9, 8, imagesx($source), imagesy($source))) {
                return null;
            }
            $bits = '';
            for ($y = 0; $y < 8; $y++) {
                $previous = $this->luminance(imagecolorat($sample, 0, $y));
                for ($x = 1; $x < 9; $x++) {
                    $current = $this->luminance(imagecolorat($sample, $x, $y));
                    $bits .= $previous > $current ? '1' : '0';
                    $previous = $current;
                }
            }
            $hex = '';
            for ($offset = 0; $offset < 64; $offset += 4) {
                $hex .= dechex(bindec(substr($bits, $offset, 4)));
            }
            return str_pad($hex, 16, '0', STR_PAD_LEFT);
        } finally {
            imagedestroy($sample);
            imagedestroy($source);
        }
    }

    public function distance(string $first, string $second): ?int
    {
        $first = strtolower(trim($first));
        $second = strtolower(trim($second));
        if (preg_match('/^[0-9a-f]{16}$/', $first) !== 1 || preg_match('/^[0-9a-f]{16}$/', $second) !== 1) {
            return null;
        }
        $counts = [0, 1, 1, 2, 1, 2, 2, 3, 1, 2, 2, 3, 2, 3, 3, 4];
        $distance = 0;
        for ($index = 0; $index < 16; $index++) {
            $distance += $counts[hexdec($first[$index]) ^ hexdec($second[$index])];
        }
        return $distance;
    }

    private function luminance(int $color): int
    {
        $red = ($color >> 16) & 0xFF;
        $green = ($color >> 8) & 0xFF;
        $blue = $color & 0xFF;
        return intdiv(($red * 299) + ($green * 587) + ($blue * 114), 1000);
    }
}

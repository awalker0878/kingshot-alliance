<?php

declare(strict_types=1);

namespace App\Domain\Content\Services;

use App\Domain\Content\ValueObjects\MediaScanResult;

use Illuminate\Http\UploadedFile;

final class BasicMediaScanner implements MediaScanner
{
    public function scan(UploadedFile $file): MediaScanResult
    {
        $path = $file->getPathname();
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return new MediaScanResult(false, 'The uploaded file could not be inspected.');
        }

        try {
            $sample = fread($handle, 131072);
        } finally {
            fclose($handle);
        }

        if ($sample === false) {
            return new MediaScanResult(false, 'The uploaded file could not be inspected.');
        }

        $lower = strtolower($sample);

        foreach (['<?php', '<script', 'powershell', 'cmd.exe', '/javascript'] as $signature) {
            if (str_contains($lower, $signature)) {
                return new MediaScanResult(false, 'The uploaded file contains a blocked executable or script signature.');
            }
        }

        if (str_starts_with($sample, 'MZ') || str_starts_with($sample, chr(127).'ELF')) {
            return new MediaScanResult(false, 'Executable files are not permitted.');
        }

        return new MediaScanResult(true);
    }
}

<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Uploads\Services;

use App\Shared\Infrastructure\Uploads\ValueObjects\UploadScanResult;
use Illuminate\Http\UploadedFile;

final class BasicUploadScanner implements UploadScanner
{
    public function scan(UploadedFile $file): UploadScanResult
    {
        $handle = fopen($file->getPathname(), 'rb');
        if ($handle === false) {
            return new UploadScanResult(false, 'The uploaded file could not be inspected.');
        }

        try {
            $sample = fread($handle, 131072);
        } finally {
            fclose($handle);
        }

        if ($sample === false) {
            return new UploadScanResult(false, 'The uploaded file could not be inspected.');
        }

        $lower = strtolower($sample);
        foreach (['<?php', '<script', 'powershell', 'cmd.exe', '/javascript'] as $signature) {
            if (str_contains($lower, $signature)) {
                return new UploadScanResult(false, 'The uploaded file contains a blocked executable or script signature.');
            }
        }

        if (str_starts_with($sample, 'MZ') || str_starts_with($sample, chr(127).'ELF')) {
            return new UploadScanResult(false, 'Executable files are not permitted.');
        }

        return new UploadScanResult(true);
    }
}

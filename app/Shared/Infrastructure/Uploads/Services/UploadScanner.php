<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Uploads\Services;

use App\Shared\Infrastructure\Uploads\ValueObjects\UploadScanResult;
use Illuminate\Http\UploadedFile;

interface UploadScanner
{
    public function scan(UploadedFile $file): UploadScanResult;
}

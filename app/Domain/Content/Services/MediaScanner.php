<?php

declare(strict_types=1);

namespace App\Domain\Content\Services;

use App\Domain\Content\ValueObjects\MediaScanResult;

use Illuminate\Http\UploadedFile;

interface MediaScanner
{
    public function scan(UploadedFile $file): MediaScanResult;
}

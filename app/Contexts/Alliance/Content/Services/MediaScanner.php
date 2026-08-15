<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Services;

use App\Contexts\Alliance\Content\ValueObjects\MediaScanResult;
use Illuminate\Http\UploadedFile;

interface MediaScanner
{
    public function scan(UploadedFile $file): MediaScanResult;
}

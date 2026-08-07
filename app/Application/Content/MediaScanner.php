<?php

declare(strict_types=1);

namespace App\Application\Content;

use Illuminate\Http\UploadedFile;

interface MediaScanner
{
    public function scan(UploadedFile $file): MediaScanResult;
}

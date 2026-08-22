<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Contracts;

use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use App\Contexts\Intelligence\Evidence\ValueObjects\OcrDocument;

interface OcrEngine
{
    public function recognize(GameEvidence $evidence): OcrDocument;
}

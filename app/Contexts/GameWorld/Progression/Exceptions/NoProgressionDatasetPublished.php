<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Progression\Exceptions;

use RuntimeException;

final class NoProgressionDatasetPublished extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('No factual progression dataset is published.');
    }
}

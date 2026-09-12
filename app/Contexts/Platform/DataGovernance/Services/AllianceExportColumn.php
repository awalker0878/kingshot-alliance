<?php

declare(strict_types=1);

namespace App\Contexts\Platform\DataGovernance\Services;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;

/** An export column expression; the grammar quotes every discovered identifier. */
final readonly class AllianceExportColumn implements Expression
{
    public function __construct(private string $name, private bool $redacted, private bool $nativeScalar) {}

    public function getValue(Grammar $grammar): string
    {
        $name = $grammar->wrap($this->name);
        $value = $this->redacted ? "'[REDACTED]'::text" : ($this->nativeScalar ? $name : $name.'::text');

        return $value.' AS '.$name;
    }
}

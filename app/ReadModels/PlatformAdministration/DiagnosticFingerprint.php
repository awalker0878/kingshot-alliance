<?php

declare(strict_types=1);

namespace App\ReadModels\PlatformAdministration;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;

/** Derive the existing fingerprint from complete error text without transferring it. */
final readonly class DiagnosticFingerprint implements Expression
{
    public function __construct(private string $column, private ?string $fallback = null) {}

    public function getValue(Grammar $grammar): string
    {
        $value = $grammar->wrap($this->column);
        if ($this->fallback !== null) {
            $value = 'COALESCE('.$value.', '.$grammar->wrap($this->fallback).')';
        }

        return "CASE WHEN $value IS NULL OR $value = '' THEN NULL ELSE substr(encode(sha256(convert_to($value, 'UTF8')), 'hex'), 1, 16) END AS error_fingerprint";
    }
}

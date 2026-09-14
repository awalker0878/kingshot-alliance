<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class TerritoryRevisionConflict extends ValidationException
{
    public function __construct(public readonly int $expectedRevision, public readonly int $currentRevision)
    {
        $validator = Validator::make([], []);
        $validator->errors()->add('revision', 'This plan changed. Review the current revision before applying your changes.');
        parent::__construct($validator);
        $this->status = 409;
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'code' => 'territory_revision_conflict',
            'message' => $this->getMessage(),
            'expected_revision' => $this->expectedRevision,
            'current_revision' => $this->currentRevision,
            'errors' => $this->errors(),
        ], 409);
    }
}

<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Events\Services;

use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

/** Secondary Event references cannot wait behind a lower governing owner scope. */
final readonly class EventLinkedReferenceState
{
    public function __construct(private AllianceReferenceQuery $alliances, private PlayerReferenceQuery $players) {}

    public function alliance(string $allianceId): AllianceReference
    {
        $this->assertTransaction();
        try {
            return $this->alliances->lockCurrentNowait($allianceId);
        } catch (QueryException $exception) {
            if (($exception->errorInfo[0] ?? null) !== '55P03') {
                throw $exception;
            }
            throw ValidationException::withMessages(['alliance' => 'The selected Alliance is changing. Retry this Event update.']);
        }
    }

    public function player(string $playerId): PlayerReference
    {
        $this->assertTransaction();
        try {
            return $this->players->lockCurrentNowait($playerId);
        } catch (QueryException $exception) {
            if (($exception->errorInfo[0] ?? null) !== '55P03') {
                throw $exception;
            }
            throw ValidationException::withMessages(['player' => 'The selected Governor is changing. Retry this Event update.']);
        }
    }

    private function assertTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Event references must be acquired inside the protected command transaction.');
        }
    }
}

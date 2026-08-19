<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Lifecycle\ValueObjects;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

final readonly class TenantContextSnapshot
{
    public function __construct(public string $allianceId, public ?string $actorPlayerId = null, public ?string $requestId = null, public ?string $traceId = null)
    {
        if (! Str::isUlid($this->allianceId)) {
            throw new InvalidArgumentException('Tenant alliance ID must be a ULID.');
        }
    }

    public static function fromRequest(Request $request): self
    {
        $allianceId = $request->attributes->get('alliance_id');
        if (! is_string($allianceId) || ! Str::isUlid($allianceId)) {
            throw new LogicException('Tenant context is not active for this request.');
        }
        $playerId = $request->attributes->get('active_player_id');

        return new self($allianceId, is_string($playerId) && Str::isUlid($playerId) ? $playerId : null, self::nullableString($request->attributes->get('request_id')), self::nullableString($request->attributes->get('trace_id')));
    }

    public function cacheKey(string $namespace, string $key): string
    {
        return sprintf('alliance:%s:%s:%s', $this->allianceId, $this->safeSegment($namespace, 'cache namespace'), $this->safeSegment($key, 'cache key'));
    }

    public function storagePath(string $relativePath): string
    {
        $relativePath = trim($relativePath, '/');
        if ($relativePath === '' || str_contains($relativePath, "\0") || str_contains($relativePath, '\\')) {
            throw new InvalidArgumentException('Tenant storage path is invalid.');
        }
        foreach (explode('/', $relativePath) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new InvalidArgumentException('Tenant storage path contains an unsafe segment.');
            }
        }

        return 'alliances/'.$this->allianceId.'/'.$relativePath;
    }

    public function exportPath(string $filename): string
    {
        if ($filename === '' || basename($filename) !== $filename) {
            throw new InvalidArgumentException('Export filename must not contain a path.');
        }

        return $this->storagePath('exports/'.$filename);
    }

    /** @return array{alliance_id:string, actor_player_id:?string, request_id:?string, trace_id:?string} */
    public function logContext(): array
    {
        return ['alliance_id' => $this->allianceId, 'actor_player_id' => $this->actorPlayerId, 'request_id' => $this->requestId, 'trace_id' => $this->traceId];
    }

    /** @return array{alliance_id:string, actor_player_id:?string, request_id:?string, trace_id:?string} */
    public function toArray(): array
    {
        return $this->logContext();
    }

    /** @param  array{alliance_id:string, actor_player_id?:?string, request_id?:?string, trace_id?:?string}  $payload */
    public static function fromArray(array $payload): self
    {
        return new self($payload['alliance_id'], $payload['actor_player_id'] ?? null, $payload['request_id'] ?? null, $payload['trace_id'] ?? null);
    }

    private function safeSegment(string $value, string $label): string
    {
        $value = trim($value);
        if ($value === '' || ! preg_match('/^[A-Za-z0-9._-]+$/', $value)) {
            throw new InvalidArgumentException(sprintf('Invalid tenant %s.', $label));
        }

        return $value;
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}

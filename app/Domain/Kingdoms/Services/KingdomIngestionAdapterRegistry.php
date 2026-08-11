<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Services;

use App\Domain\Kingdoms\Contracts\KingdomIngestionAdapter;
use App\Domain\Kingdoms\Enums\KingdomIngestionTargetKind;
use Illuminate\Contracts\Container\Container;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class KingdomIngestionAdapterRegistry
{
    public function __construct(private Container $container) {}

    /** @return list<array{key: string, version: string, label: string, targetKinds: list<string>}> */
    public function definitions(): array
    {
        $definitions = [];

        foreach ($this->adapters() as $adapter) {
            $definitions[] = [
                'key' => $adapter->key(),
                'version' => $adapter->version(),
                'label' => $adapter->label(),
                'targetKinds' => array_map(
                    static fn (KingdomIngestionTargetKind $kind): string => $kind->value,
                    $adapter->supportedTargetKinds(),
                ),
            ];
        }

        return $definitions;
    }

    public function require(string $key): KingdomIngestionAdapter
    {
        foreach ($this->adapters() as $adapter) {
            if ($adapter->key() === $key) {
                return $adapter;
            }
        }

        throw ValidationException::withMessages([
            'adapter_key' => 'That automated-ingestion source adapter is not approved.',
        ]);
    }

    /** @return list<KingdomIngestionAdapter> */
    private function adapters(): array
    {
        $configured = config('kingdoms.ingestion_adapters', []);
        if (! is_array($configured)) {
            throw new LogicException('Kingdom ingestion adapters must be configured as a list.');
        }

        $adapters = [];
        $keys = [];

        foreach ($configured as $class) {
            if (! is_string($class) || $class === '') {
                throw new LogicException('Every Kingdom ingestion adapter configuration entry must be a class name.');
            }

            $adapter = $this->container->make($class);
            if (! $adapter instanceof KingdomIngestionAdapter) {
                throw new LogicException($class.' must implement '.KingdomIngestionAdapter::class.'.');
            }

            $this->validateDefinition($adapter);
            if (isset($keys[$adapter->key()])) {
                throw new LogicException('Duplicate Kingdom ingestion adapter key: '.$adapter->key());
            }

            $keys[$adapter->key()] = true;
            $adapters[] = $adapter;
        }

        return $adapters;
    }

    private function validateDefinition(KingdomIngestionAdapter $adapter): void
    {
        if (preg_match('/^[a-z0-9][a-z0-9._-]{0,79}$/', $adapter->key()) !== 1) {
            throw new LogicException('Kingdom ingestion adapter keys must be lowercase stable identifiers.');
        }

        if ($adapter->version() === '' || mb_strlen($adapter->version()) > 40) {
            throw new LogicException('Kingdom ingestion adapter versions must be 1-40 characters.');
        }

        if ($adapter->label() === '' || mb_strlen($adapter->label()) > 120) {
            throw new LogicException('Kingdom ingestion adapter labels must be 1-120 characters.');
        }

        $kinds = $adapter->supportedTargetKinds();
        if ($kinds === []) {
            throw new LogicException('Kingdom ingestion adapters must declare at least one supported target kind.');
        }

        foreach ($kinds as $kind) {
            if (! $kind instanceof KingdomIngestionTargetKind) {
                throw new LogicException('Kingdom ingestion adapter target kinds must use the canonical enum.');
            }
        }
    }
}

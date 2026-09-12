<?php

declare(strict_types=1);

namespace App\Contexts\Platform\DataGovernance\Services;

use RuntimeException;

final class AllianceExportBuffer
{
    public const MAX_BYTES = 104857600;

    /** @var resource */
    private $stream;

    private int $bytes = 0;

    public function __construct()
    {
        $stream = tmpfile();
        if ($stream === false) {
            throw new RuntimeException('Could not prepare the Alliance export.');
        }
        $this->stream = $stream;
    }

    public function assertFits(int $bytes): void
    {
        if ($bytes > self::MAX_BYTES - $this->bytes) {
            throw new RuntimeException('Alliance export exceeded the 100 MiB synchronous export safety limit.');
        }
    }

    public function write(string $contents): void
    {
        $length = strlen($contents);
        $this->assertFits($length);
        if (fwrite($this->stream, $contents) !== $length) {
            throw new RuntimeException('Could not finish preparing the Alliance export.');
        }
        $this->bytes += $length;
    }

    public function sha256(): string
    {
        rewind($this->stream);
        $hash = hash_init('sha256');
        hash_update_stream($hash, $this->stream);

        return hash_final($hash);
    }

    public function send(): void
    {
        rewind($this->stream);
        fpassthru($this->stream);
    }

    /** @return resource */
    public function stream()
    {
        rewind($this->stream);

        return $this->stream;
    }

    public function __destruct()
    {
        fclose($this->stream);
    }
}

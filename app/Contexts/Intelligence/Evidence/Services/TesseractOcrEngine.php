<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Services;

use App\Contexts\Intelligence\Evidence\Contracts\OcrEngine;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use App\Contexts\Intelligence\Evidence\ValueObjects\OcrDocument;
use App\Contexts\Intelligence\Evidence\ValueObjects\OcrToken;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class TesseractOcrEngine implements OcrEngine
{
    public function recognize(GameEvidence $evidence): OcrDocument
    {
        $binary = trim((string) config('evidence.ocr.binary', 'tesseract'));
        $language = trim((string) config('evidence.ocr.language', 'eng'));
        $psm = max(3, min(13, (int) config('evidence.ocr.page_segmentation_mode', 6)));
        if ($binary === '' || $language === '') {
            throw new RuntimeException('Evidence OCR is not configured.');
        }

        $stream = Storage::disk((string) $evidence->disk)->readStream((string) $evidence->path);
        if (! is_resource($stream)) {
            throw new RuntimeException('Evidence source could not be opened for OCR.');
        }

        $temporary = tempnam(sys_get_temp_dir(), 'ks-evidence-');
        if ($temporary === false) {
            fclose($stream);
            throw new RuntimeException('Evidence OCR temporary storage is unavailable.');
        }

        try {
            $target = fopen($temporary, 'wb');
            if ($target === false) {
                throw new RuntimeException('Evidence OCR temporary storage could not be opened.');
            }
            try {
                stream_copy_to_stream($stream, $target);
            } finally {
                fclose($target);
                fclose($stream);
            }

            [$status, $stdout, $stderr] = $this->run([$binary, $temporary, 'stdout', '-l', $language, '--psm', (string) $psm, 'tsv']);
            if ($status !== 0) {
                throw new RuntimeException('Evidence OCR failed: '.trim(substr($stderr, 0, 240)));
            }

            return new OcrDocument(
                engine: 'tesseract',
                engineVersion: $this->version($binary),
                language: $language,
                tokens: $this->tokens($stdout),
            );
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
            @unlink($temporary);
        }
    }

    /**
     * @param list<string> $command
     * @return array{0:int,1:string,2:string}
     */
    private function run(array $command): array
    {
        $pipes = [];
        $process = @proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (! is_resource($process)) {
            throw new RuntimeException('Evidence OCR process could not be started.');
        }

        $stdout = isset($pipes[1]) && is_resource($pipes[1]) ? stream_get_contents($pipes[1]) : false;
        $stderr = isset($pipes[2]) && is_resource($pipes[2]) ? stream_get_contents($pipes[2]) : false;
        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }
        $status = proc_close($process);

        return [$status, is_string($stdout) ? $stdout : '', is_string($stderr) ? $stderr : ''];
    }

    private function version(string $binary): string
    {
        try {
            [$status, $stdout] = $this->run([$binary, '--version']);
            if ($status === 0 && preg_match('/tesseract\s+([^\s]+)/i', $stdout, $match) === 1) {
                return $match[1];
            }
        } catch (RuntimeException) {
        }

        return 'unknown';
    }

    /** @return list<OcrToken> */
    private function tokens(string $tsv): array
    {
        $tokens = [];
        $lines = preg_split('/\R/', $tsv) ?: [];
        foreach (array_slice($lines, 1) as $line) {
            if (trim($line) === '') {
                continue;
            }
            $columns = explode("\t", $line, 12);
            if (count($columns) < 12 || (int) $columns[0] !== 5) {
                continue;
            }
            $text = trim((string) $columns[11]);
            $confidence = (float) $columns[10];
            if ($text === '' || $confidence < 0) {
                continue;
            }
            $tokens[] = new OcrToken(
                text: $text,
                confidence: max(0.0, min(1.0, $confidence / 100.0)),
                page: (int) $columns[1],
                block: (int) $columns[2],
                paragraph: (int) $columns[3],
                line: (int) $columns[4],
                word: (int) $columns[5],
                left: (int) $columns[6],
                top: (int) $columns[7],
                width: (int) $columns[8],
                height: (int) $columns[9],
            );
        }

        return $tokens;
    }
}

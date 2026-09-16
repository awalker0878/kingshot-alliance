<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Services;

use Illuminate\Validation\ValidationException;
use JsonException;

final readonly class TerritoryLayoutDocumentContract
{
    public function __construct(
        private TerritoryLayoutContract $layout,
        private TerritoryAnnotationContract $annotations,
    ) {}

    /** @return array<string, mixed> */
    public function decode(string $json): array
    {
        if (strlen($json) > 5_000_000) {
            throw ValidationException::withMessages(['document' => 'The document exceeds the five megabyte limit.']);
        }

        try {
            $raw = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ValidationException::withMessages(['document' => 'The document is not valid JSON.']);
        }
        if (! is_array($raw) || array_is_list($raw)) {
            throw ValidationException::withMessages(['document' => 'The document must be a JSON object.']);
        }
        if (array_diff(array_keys($raw), ['schema_version', 'plan', 'alliances', 'groups', 'objects', 'annotations']) !== []) {
            throw ValidationException::withMessages(['document' => 'The document contains unsupported fields.']);
        }

        $annotationRows = $this->annotationRows($raw['annotations'] ?? []);
        unset($raw['annotations']);

        $core = $this->layout->decode(json_encode(
            $raw,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        ));
        $allianceKeys = [];
        foreach ($core['alliances'] as $alliance) {
            $key = $alliance['key'] ?? null;
            if (! is_string($key) || $key === '') {
                throw new \LogicException('Normalized Territory Alliance keys are invalid.');
            }
            $allianceKeys[] = $key;
        }
        $core['annotations'] = $this->annotations->normalize($annotationRows, $allianceKeys);

        return $core;
    }

    /** @return list<array<string, mixed>> */
    private function annotationRows(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            throw ValidationException::withMessages(['annotations' => 'Annotations must be a list.']);
        }

        $rows = [];
        foreach ($value as $row) {
            if (! is_array($row) || array_is_list($row)) {
                throw ValidationException::withMessages(['annotations' => 'Every annotation must be an object.']);
            }
            $entry = [];
            foreach ($row as $key => $item) {
                if (! is_string($key)) {
                    throw ValidationException::withMessages(['annotations' => 'Annotation field names must be strings.']);
                }
                $entry[$key] = $item;
            }
            $rows[] = $entry;
        }

        return $rows;
    }
}

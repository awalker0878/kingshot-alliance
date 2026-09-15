<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Services;

use JsonException;
use Illuminate\Validation\ValidationException;

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

        $annotationRows = $raw['annotations'] ?? [];
        if (! is_array($annotationRows)) {
            throw ValidationException::withMessages(['annotations' => 'Annotations must be a list.']);
        }
        unset($raw['annotations']);

        $core = $this->layout->decode(json_encode(
            $raw,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        ));
        $allianceKeys = [];
        foreach ($core['alliances'] as $alliance) {
            if (is_array($alliance) && isset($alliance['key']) && is_string($alliance['key'])) {
                $allianceKeys[] = $alliance['key'];
            }
        }
        $core['annotations'] = $this->annotations->normalize($annotationRows, $allianceKeys);

        return $core;
    }
}

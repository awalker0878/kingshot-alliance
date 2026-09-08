<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Services;

use App\Contexts\Intelligence\Evidence\Models\EvidenceClassificationAttempt;
use App\Contexts\Intelligence\Evidence\Models\EvidenceExtractedField;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use App\Contexts\Intelligence\Evidence\Models\ProgressionNormalizationAttempt;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class EvidenceRedactor
{
    public function redact(GameEvidence $evidence, string $reason): void
    {
        $path = $evidence->path;
        if (is_string($path) && $path !== '') {
            if (! Storage::disk((string) $evidence->disk)->delete($path)) {
                throw new RuntimeException('The private Evidence binary could not be deleted.');
            }
        }

        EvidenceClassificationAttempt::query()
            ->where('evidence_id', $evidence->id)
            ->update([
                'ocr_payload' => null,
                'raw_text' => null,
            ]);

        EvidenceExtractedField::query()
            ->whereIn('extraction_attempt_id', $evidence->getConnection()
                ->table('evidence_extraction_attempts')
                ->select('id')
                ->where('evidence_id', $evidence->id))
            ->update([
                'raw_text' => '',
                'normalized_value' => '',
                'bounding_box' => null,
                'warnings' => null,
            ]);

        ProgressionNormalizationAttempt::query()
            ->where('evidence_id', $evidence->id)
            ->update(['normalized_payload' => '[]', 'warnings' => null]);

        $evidence->forceFill([
            'path' => null,
            'original_name' => '[redacted]',
            'binary_deleted_at' => $evidence->binary_deleted_at ?? now(),
            'redacted_at' => now(),
            'deletion_reason' => $reason,
        ])->save();
    }
}

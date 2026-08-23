<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Services;

use App\Contexts\Intelligence\Evidence\Models\EvidenceClassificationAttempt;
use App\Contexts\Intelligence\Evidence\Models\EvidenceExtractedField;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use Illuminate\Support\Facades\Storage;

final class EvidenceRedactor
{
    public function redact(GameEvidence $evidence, string $reason): void
    {
        $path = $evidence->path;
        if (is_string($path) && $path !== '') {
            Storage::disk((string) $evidence->disk)->delete($path);
        }

        EvidenceClassificationAttempt::query()
            ->where('evidence_id', $evidence->id)
            ->update([
                'ocr_payload' => null,
                'raw_text' => null,
            ]);

        $attemptIds = $evidence->getConnection()
            ->table('evidence_extraction_attempts')
            ->where('evidence_id', $evidence->id)
            ->pluck('id');

        if ($attemptIds->isNotEmpty()) {
            EvidenceExtractedField::query()
                ->whereIn('extraction_attempt_id', $attemptIds)
                ->update([
                    'raw_text' => '',
                    'bounding_box' => null,
                ]);
        }

        $evidence->forceFill([
            'path' => null,
            'original_name' => '[redacted]',
            'binary_deleted_at' => $evidence->binary_deleted_at ?? now(),
            'redacted_at' => now(),
            'deletion_reason' => $reason,
        ])->save();
    }
}

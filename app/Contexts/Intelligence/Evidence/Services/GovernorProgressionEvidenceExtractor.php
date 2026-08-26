<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Services;

use App\Contexts\Intelligence\Evidence\Contracts\EvidenceExtractor;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Evidence\ValueObjects\ExtractedFieldCandidate;
use App\Contexts\Intelligence\Evidence\ValueObjects\OcrDocument;
use App\Contexts\Intelligence\Evidence\ValueObjects\OcrToken;
use InvalidArgumentException;

final readonly class GovernorProgressionEvidenceExtractor implements EvidenceExtractor
{
    public function __construct(private GovernorProgressionEvidenceSchemaRegistry $schemas) {}

    public function key(EvidenceKind $kind): string
    {
        $this->schemas->require($kind);

        return 'governor-progression-fixture-extractor';
    }

    public function version(EvidenceKind $kind): string
    {
        $this->schemas->require($kind);

        return '1.0.1';
    }

    public function schemaVersion(EvidenceKind $kind): string
    {
        return $this->schemas->require($kind)->version;
    }

    public function supports(EvidenceKind $kind): bool
    {
        return $kind->isGovernorProgression();
    }

    public function extract(EvidenceKind $kind, OcrDocument $document): array
    {
        $schema = $this->schemas->require($kind);
        $fields = match ($kind) {
            EvidenceKind::GovernorProfile => $this->profile($document),
            EvidenceKind::GovernorHeroRoster => $this->heroRoster($document),
            EvidenceKind::GovernorHeroDetail => $this->heroDetail($document),
            EvidenceKind::GovernorHeroGear => $this->heroGear($document),
            EvidenceKind::GovernorGear => $this->governorGear($document),
            EvidenceKind::GovernorCharms => $this->charms($document),
            default => throw new InvalidArgumentException('Unsupported Governor Progression screenshot kind.'),
        };

        foreach ($fields as $field) {
            if (! in_array($field->fieldKey, $schema->supportedFields, true)) {
                throw new InvalidArgumentException('Governor Progression extractor emitted a field outside its registered schema.');
            }
        }

        return $fields;
    }

    /** @return list<ExtractedFieldCandidate> */
    private function profile(OcrDocument $document): array
    {
        $fields = [];
        foreach ($document->lines() as $line) {
            $text = $this->lineText($line);
            if (preg_match('/\b(?:governor|name)\s*[:\-]\s*([\pL\pN][\pL\pN ._\-]{1,79})/iu', $text, $match) === 1) {
                $fields[] = $this->candidate('observed_name', 0, $line, trim($match[1]), 'string');
            }
            if (
                preg_match('/\b(?:governor\s+)?power\s*[:\-]?\s*([\d, ]{1,24})\b/i', $text, $match) === 1
                && ($value = $this->integer($match[1])) !== null
            ) {
                $fields[] = $this->candidate('power', 0, $line, (string) $value, 'integer');
            }
            if (preg_match('/\b(?:town\s*center|tc|progression(?:\s+level)?)\s*[:\-]?\s*([A-Za-z0-9 +._-]{1,32})/i', $text, $match) === 1) {
                $fields[] = $this->candidate('progression_level', 0, $line, trim($match[1]), 'string');
            }
            if (preg_match('/\b(?:alliance|alliance\s+tag)\s*[:\-]\s*\[?([A-Za-z0-9_-]{1,16})\]?/i', $text, $match) === 1) {
                $fields[] = $this->candidate('observed_alliance_tag', 0, $line, trim($match[1]), 'string');
            }
            if (preg_match('/\bkingdom\s*#?\s*(\d{1,6})\b/i', $text, $match) === 1) {
                $fields[] = $this->candidate('kingdom_number', 0, $line, (string) ((int) $match[1]), 'integer');
            }
        }

        return $this->dedupe($fields);
    }

    /** @return list<ExtractedFieldCandidate> */
    private function heroRoster(OcrDocument $document): array
    {
        $fields = [];
        $ordinal = 0;
        foreach ($document->lines() as $line) {
            $text = $this->lineText($line);
            $name = $this->heroNameFromRow($text);
            if ($name === null) {
                continue;
            }
            $fields[] = $this->candidate('hero_name', $ordinal, $line, $name, 'string');
            $this->appendHeroNumbers($fields, $line, $text, $ordinal, false);
            $ordinal++;
        }

        return $fields;
    }

    /** @return list<ExtractedFieldCandidate> */
    private function heroDetail(OcrDocument $document): array
    {
        $fields = [];
        $nameFound = false;
        foreach ($document->lines() as $line) {
            $text = $this->lineText($line);
            if (! $nameFound && preg_match('/\b(?:hero|hero\s+name|name)\s*[:\-]\s*([\pL\pN][\pL\pN ._\-]{1,79})/iu', $text, $match) === 1) {
                $fields[] = $this->candidate('hero_name', 0, $line, trim($match[1]), 'string');
                $nameFound = true;
            }
            $this->appendHeroNumbers($fields, $line, $text, 0, true);
        }

        return $this->dedupe($fields);
    }

    /** @return list<ExtractedFieldCandidate> */
    private function heroGear(OcrDocument $document): array
    {
        $fields = [];
        $heroFound = false;
        $ordinal = 0;
        foreach ($document->lines() as $line) {
            $text = $this->lineText($line);
            if (! $heroFound && preg_match('/\b(?:hero|hero\s+name)\s*[:\-]\s*([\pL\pN][\pL\pN ._\-]{1,79})/iu', $text, $match) === 1) {
                $fields[] = $this->candidate('hero_name', 0, $line, trim($match[1]), 'string');
                $heroFound = true;
            }
            $slot = $this->gearSlot($text);
            if ($slot === null) {
                continue;
            }
            $fields[] = $this->candidate('gear_slot', $ordinal, $line, $slot, 'string');
            if (($quality = $this->gearQuality($text)) !== null) {
                $fields[] = $this->candidate('gear_quality', $ordinal, $line, $quality, 'string');
            }
            if (preg_match('/\b(?:gear\s+)?(?:level|lv\.?)\s*[:\-]?\s*(\d{1,3})\b/i', $text, $match) === 1) {
                $fields[] = $this->candidate('gear_level', $ordinal, $line, (string) ((int) $match[1]), 'integer');
            }
            if (preg_match('/\bmastery(?:\s+(?:forge|level))?\s*[:\-]?\s*(\d{1,3})\b/i', $text, $match) === 1) {
                $fields[] = $this->candidate('mastery_level', $ordinal, $line, (string) ((int) $match[1]), 'integer');
            }
            $ordinal++;
        }

        return $fields;
    }

    /** @return list<ExtractedFieldCandidate> */
    private function governorGear(OcrDocument $document): array
    {
        $fields = [];
        $ordinal = 0;
        foreach ($document->lines() as $line) {
            $text = $this->lineText($line);
            $slot = $this->gearSlot($text);
            if ($slot === null) {
                continue;
            }
            $fields[] = $this->candidate('gear_slot', $ordinal, $line, $slot, 'string');
            if (($quality = $this->gearQuality($text)) !== null) {
                $fields[] = $this->candidate('gear_quality', $ordinal, $line, $quality, 'string');
            }
            if (preg_match('/\b(?:gear\s+)?(?:level|lv\.?)\s*[:\-]?\s*(\d{1,3})\b/i', $text, $match) === 1) {
                $fields[] = $this->candidate('gear_level', $ordinal, $line, (string) ((int) $match[1]), 'integer');
            }
            if (preg_match('/\b(?:stars?|star)\s*[:\-]?\s*(\d{1,2})\b/i', $text, $match) === 1) {
                $fields[] = $this->candidate('gear_star', $ordinal, $line, (string) ((int) $match[1]), 'integer');
            }
            $ordinal++;
        }

        return $fields;
    }

    /** @return list<ExtractedFieldCandidate> */
    private function charms(OcrDocument $document): array
    {
        $fields = [];
        $ordinal = 0;
        foreach ($document->lines() as $line) {
            $text = $this->lineText($line);
            $slot = $this->charmSlot($text);
            if ($slot === null) {
                continue;
            }
            $fields[] = $this->candidate('charm_slot', $ordinal, $line, $slot, 'string');
            if (preg_match('/\b(?:charm\s+name|charm|name)\s*[:\-]\s*([\pL\pN][\pL\pN ._\-]{1,48})/iu', $text, $match) === 1) {
                $fields[] = $this->candidate('charm_name', $ordinal, $line, trim($match[1]), 'string');
            }
            if (preg_match('/\b(?:charm\s+)?(?:level|lv\.?)\s*[:\-]?\s*(\d{1,2})\b/i', $text, $match) === 1) {
                $fields[] = $this->candidate('charm_level', $ordinal, $line, (string) ((int) $match[1]), 'integer');
            }
            $ordinal++;
        }

        return $fields;
    }

    /**
     * @param  list<ExtractedFieldCandidate>  $fields
     * @param  list<OcrToken>  $line
     */
    private function appendHeroNumbers(array &$fields, array $line, string $text, int $ordinal, bool $includeSubstar): void
    {
        if (preg_match('/\b(?:level|lv\.?)\s*[:\-]?\s*(\d{1,2})\b/i', $text, $match) === 1) {
            $fields[] = $this->candidate('level', $ordinal, $line, (string) ((int) $match[1]), 'integer');
        }
        if (preg_match('/\b(?:stars?|star)\s*[:\-]?\s*(\d{1,2})\b/i', $text, $match) === 1) {
            $fields[] = $this->candidate('star', $ordinal, $line, (string) ((int) $match[1]), 'integer');
        }
        if ($includeSubstar && preg_match('/\b(?:substars?|substar)\s*[:\-]?\s*(\d{1,2})\b/i', $text, $match) === 1) {
            $fields[] = $this->candidate('substar', $ordinal, $line, (string) ((int) $match[1]), 'integer');
        }
        if (preg_match('/\bwidget(?:\s+level)?\s*[:\-]?\s*(\d{1,2})\b/i', $text, $match) === 1) {
            $fields[] = $this->candidate('widget_level', $ordinal, $line, (string) ((int) $match[1]), 'integer');
        }
    }

    private function heroNameFromRow(string $text): ?string
    {
        if (preg_match('/\b(?:hero|name)\s*[:\-]\s*([\pL\pN][\pL\pN ._\-]{1,79}?)(?=\s+(?:level|lv\.?|stars?|widget)\b|$)/iu', $text, $match) === 1) {
            return trim($match[1]);
        }
        if (preg_match('/^\s*([\pL][\pL\pN ._\-]{1,48}?)\s+(?=(?:level|lv\.?|stars?|widget)\b)/iu', $text, $match) === 1) {
            return trim($match[1]);
        }

        return null;
    }

    private function gearQuality(string $text): ?string
    {
        if (preg_match('/\b(?:quality|rarity|tier)\s*[:\-]?\s*([A-Za-z][A-Za-z0-9 _-]{0,23}?)(?=\s+(?:(?:gear\s+)?(?:level|lv\.?)|mastery(?:\s+(?:forge|level))?|stars?|star)\b|$)/i', $text, $match) !== 1) {
            return null;
        }

        $quality = trim($match[1]);

        return $quality === '' ? null : $quality;
    }

    private function gearSlot(string $text): ?string
    {
        $aliases = [
            'helmet' => ['helmet', 'helm', 'headwear'],
            'chest' => ['chest', 'armor', 'armour', 'coat'],
            'gloves' => ['gloves', 'gauntlets'],
            'boots' => ['boots', 'shoes'],
            'belt' => ['belt'],
            'weapon' => ['weapon', 'sword', 'blade'],
            'pants' => ['pants', 'trousers'],
            'watch' => ['watch'],
            'cap' => ['cap', 'hat'],
            'accessory' => ['accessory', 'accessories'],
        ];
        $lower = mb_strtolower($text);
        foreach ($aliases as $canonical => $terms) {
            foreach ($terms as $term) {
                if (preg_match('/\b'.preg_quote($term, '/').'\b/i', $lower) === 1) {
                    return $canonical;
                }
            }
        }

        return null;
    }

    private function charmSlot(string $text): ?string
    {
        $lower = mb_strtolower($text);
        foreach (['infantry', 'cavalry', 'archer'] as $troop) {
            if (str_contains($lower, $troop) && str_contains($lower, 'charm')) {
                if (preg_match('/\b(?:slot\s*)?(\d{1,2})\b/i', $text, $match) === 1) {
                    return $troop.'-'.$match[1];
                }

                return $troop;
            }
        }
        if (preg_match('/\bcharm\s*(?:slot)?\s*#?\s*(\d{1,2})\b/i', $text, $match) === 1) {
            return 'slot-'.$match[1];
        }

        return null;
    }

    /** @param  list<OcrToken>  $tokens */
    private function lineText(array $tokens): string
    {
        return trim(implode(' ', array_map(static fn (OcrToken $token): string => $token->text, $tokens)));
    }

    private function integer(string $value): ?int
    {
        $compact = str_replace([',', ' ', ':'], '', trim($value));
        if ($compact === '' || preg_match('/^\d+$/', $compact) !== 1 || strlen($compact) > 18) {
            return null;
        }

        return (int) $compact;
    }

    /**
     * @param  list<OcrToken>  $tokens
     * @param  list<string>  $warnings
     */
    private function candidate(string $key, int $ordinal, array $tokens, string $normalized, string $type, array $warnings = []): ExtractedFieldCandidate
    {
        if ($tokens === []) {
            throw new InvalidArgumentException('Governor Progression extraction candidates require at least one OCR token.');
        }

        $confidenceTotal = 0.0;
        $left = $tokens[0]->left;
        $top = $tokens[0]->top;
        $right = $tokens[0]->left + $tokens[0]->width;
        $bottom = $tokens[0]->top + $tokens[0]->height;
        foreach ($tokens as $token) {
            $confidenceTotal += $token->confidence;
            $left = min($left, $token->left);
            $top = min($top, $token->top);
            $right = max($right, $token->left + $token->width);
            $bottom = max($bottom, $token->top + $token->height);
        }

        return new ExtractedFieldCandidate(
            fieldKey: $key,
            rowOrdinal: $ordinal,
            rawText: $this->lineText($tokens),
            normalizedValue: $normalized,
            dataType: $type,
            confidence: max(0.0, min(1.0, $confidenceTotal / count($tokens))),
            boundingBox: ['left' => $left, 'top' => $top, 'width' => $right - $left, 'height' => $bottom - $top],
            warnings: $warnings,
        );
    }

    /**
     * @param  list<ExtractedFieldCandidate>  $fields
     * @return list<ExtractedFieldCandidate>
     */
    private function dedupe(array $fields): array
    {
        $seen = [];
        $result = [];
        foreach ($fields as $field) {
            $key = $field->fieldKey.':'.$field->rowOrdinal;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $result[] = $field;
        }

        return $result;
    }
}

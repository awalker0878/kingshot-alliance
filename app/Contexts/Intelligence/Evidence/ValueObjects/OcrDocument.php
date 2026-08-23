<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\ValueObjects;

final readonly class OcrDocument
{
    /** @param list<OcrToken> $tokens */
    public function __construct(
        public string $engine,
        public string $engineVersion,
        public string $language,
        public array $tokens,
    ) {}

    public function text(): string
    {
        return implode("\n", array_map(
            static fn (array $line): string => implode(' ', array_map(static fn (OcrToken $token): string => $token->text, $line)),
            $this->lines(),
        ));
    }

    /** @return list<list<OcrToken>> */
    public function lines(): array
    {
        $grouped = [];
        foreach ($this->tokens as $token) {
            $key = implode(':', [$token->page, $token->block, $token->paragraph, $token->line]);
            $grouped[$key][] = $token;
        }
        foreach ($grouped as &$line) {
            usort($line, static fn (OcrToken $a, OcrToken $b): int => $a->word <=> $b->word);
        }
        unset($line);
        $lines = array_values($grouped);
        usort($lines, static function (array $a, array $b): int {
            $aTop = min(array_map(static fn (OcrToken $token): int => $token->top, $a));
            $bTop = min(array_map(static fn (OcrToken $token): int => $token->top, $b));
            if ($aTop !== $bTop) {
                return $aTop <=> $bTop;
            }
            $aLeft = min(array_map(static fn (OcrToken $token): int => $token->left, $a));
            $bLeft = min(array_map(static fn (OcrToken $token): int => $token->left, $b));

            return $aLeft <=> $bLeft;
        });

        return $lines;
    }

    /** @return array{engine:string,engineVersion:string,language:string,tokens:list<array<string,int|float|string>>} */
    public function toArray(): array
    {
        return [
            'engine' => $this->engine,
            'engineVersion' => $this->engineVersion,
            'language' => $this->language,
            'tokens' => array_map(static fn (OcrToken $token): array => $token->toArray(), $this->tokens),
        ];
    }

    /** @param array<string,mixed> $value */
    public static function fromArray(array $value): self
    {
        $tokens = is_array($value['tokens'] ?? null) ? $value['tokens'] : [];

        return new self(
            engine: (string) ($value['engine'] ?? 'unknown'),
            engineVersion: (string) ($value['engineVersion'] ?? 'unknown'),
            language: (string) ($value['language'] ?? 'unknown'),
            tokens: array_values(array_map(
                static fn (mixed $token): OcrToken => OcrToken::fromArray(is_array($token) ? $token : []),
                $tokens,
            )),
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\ValueObjects;

final readonly class OcrToken
{
    public function __construct(
        public string $text,
        public float $confidence,
        public int $page,
        public int $block,
        public int $paragraph,
        public int $line,
        public int $word,
        public int $left,
        public int $top,
        public int $width,
        public int $height,
    ) {}

    /** @return array<string,int|float|string> */
    public function toArray(): array
    {
        return [
            'text' => $this->text,
            'confidence' => $this->confidence,
            'page' => $this->page,
            'block' => $this->block,
            'paragraph' => $this->paragraph,
            'line' => $this->line,
            'word' => $this->word,
            'left' => $this->left,
            'top' => $this->top,
            'width' => $this->width,
            'height' => $this->height,
        ];
    }

    /** @param array<string,mixed> $value */
    public static function fromArray(array $value): self
    {
        return new self(
            text: (string) ($value['text'] ?? ''),
            confidence: (float) ($value['confidence'] ?? 0.0),
            page: (int) ($value['page'] ?? 0),
            block: (int) ($value['block'] ?? 0),
            paragraph: (int) ($value['paragraph'] ?? 0),
            line: (int) ($value['line'] ?? 0),
            word: (int) ($value['word'] ?? 0),
            left: (int) ($value['left'] ?? 0),
            top: (int) ($value['top'] ?? 0),
            width: (int) ($value['width'] ?? 0),
            height: (int) ($value['height'] ?? 0),
        );
    }
}

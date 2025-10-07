<?php

namespace App\Data;

class SanitizedHtml
{
    /**
     * @param list<string> $blockedElements
     * @param list<string> $blockedAttributes
     * @param list<string> $blockedProtocols
     */
    public function __construct(
        public readonly string $original,
        public readonly string $sanitized,
        public readonly bool $modified,
        public readonly array $blockedElements = [],
        public readonly array $blockedAttributes = [],
        public readonly array $blockedProtocols = [],
    ) {
    }

    public function preview(int $limit = 120): string
    {
        $text = strip_tags($this->sanitized);
        $text = preg_replace('/\s+/', ' ', $text ?? '') ?: '';
        $text = trim($text);

        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $limit), " \t\n\r\0\v\xC2\xA0").'…';
    }
}

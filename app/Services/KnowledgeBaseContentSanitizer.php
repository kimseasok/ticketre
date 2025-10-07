<?php

namespace App\Services;

use App\Data\SanitizedHtml;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

class KnowledgeBaseContentSanitizer
{
    /**
     * @var list<string>
     */
    protected array $allowedElements;

    /**
     * @var array<string, list<string>>
     */
    protected array $allowedAttributes;

    /**
     * @var list<string>
     */
    protected array $allowedSchemes;

    protected int $maxInputLength;

    protected HtmlSanitizerInterface $sanitizer;

    public function __construct(array $config = [], ?HtmlSanitizerInterface $sanitizer = null)
    {
        $config = $config ?: config('sanitizer.knowledge_base', []);

        $this->allowedElements = array_values(array_map('strtolower', $config['allowed_elements'] ?? []));
        $this->allowedAttributes = [];
        foreach ($config['allowed_attributes'] ?? [] as $element => $attributes) {
            $this->allowedAttributes[strtolower($element)] = array_values(array_map('strtolower', $attributes));
        }

        $this->allowedSchemes = array_values(array_map('strtolower', $config['allowed_schemes'] ?? ['http', 'https']));
        $this->maxInputLength = (int) ($config['max_input_length'] ?? 20000);

        $sanitizerConfig = (new HtmlSanitizerConfig())
            ->allowSafeElements()
            ->allowLinkSchemes($this->allowedSchemes)
            ->allowMediaSchemes($this->allowedSchemes)
            ->withMaxInputLength($this->maxInputLength);

        if (($config['allow_relative_links'] ?? false) === true) {
            $sanitizerConfig = $sanitizerConfig->allowRelativeLinks();
        }

        if (($config['allow_relative_media'] ?? false) === true) {
            $sanitizerConfig = $sanitizerConfig->allowRelativeMedias();
        }

        foreach ($config['blocked_elements'] ?? [] as $blocked) {
            $sanitizerConfig = $sanitizerConfig->dropElement($blocked);
        }

        foreach ($this->allowedElements as $element) {
            $attributes = array_values(array_unique(array_merge(
                $this->allowedAttributes[$element] ?? [],
                $this->allowedAttributes['*'] ?? []
            )));

            if ($attributes === []) {
                $sanitizerConfig = $sanitizerConfig->allowElement($element);
            } else {
                $sanitizerConfig = $sanitizerConfig->allowElement($element, $attributes);
            }
        }

        if (isset($this->allowedAttributes['*'])) {
            foreach ($this->allowedAttributes['*'] as $attribute) {
                $sanitizerConfig = $sanitizerConfig->allowAttribute($attribute, '*');
            }
        }

        foreach ($this->allowedAttributes as $element => $attributes) {
            if ($element === '*') {
                continue;
            }

            foreach ($attributes as $attribute) {
                $sanitizerConfig = $sanitizerConfig->allowAttribute($attribute, $element);
            }
        }

        $this->sanitizer = $sanitizer ?? new HtmlSanitizer($sanitizerConfig);
    }

    public function sanitize(string $html): SanitizedHtml
    {
        $sanitized = trim($this->sanitizer->sanitize($html));
        $normalizedOriginal = $this->normalize($html);
        $normalizedSanitized = $this->normalize($sanitized);

        $blockedElements = $this->detectBlockedElements($html);
        $blockedAttributes = $this->detectBlockedAttributes($html);
        $blockedProtocols = $this->detectBlockedProtocols($html);

        $modified = $normalizedOriginal !== $normalizedSanitized
            || $blockedElements !== []
            || $blockedAttributes !== []
            || $blockedProtocols !== [];

        return new SanitizedHtml(
            original: $html,
            sanitized: $sanitized,
            modified: $modified,
            blockedElements: $blockedElements,
            blockedAttributes: $blockedAttributes,
            blockedProtocols: $blockedProtocols,
        );
    }

    protected function normalize(string $html): string
    {
        $normalized = preg_replace('/\s+/', ' ', trim($html));

        return $normalized !== null ? $normalized : '';
    }

    /**
     * @return list<string>
     */
    protected function detectBlockedElements(string $html): array
    {
        $blocked = [];
        if ($html === '') {
            return [];
        }

        preg_match_all('/<\/?([a-z0-9:-]+)[^>]*>/i', $html, $matches);

        foreach ($matches[1] ?? [] as $element) {
            $name = strtolower($element);
            if (in_array($name, ['html', 'body', 'head'], true)) {
                continue;
            }

            if (! in_array($name, $this->allowedElements, true)) {
                $blocked[$name] = true;
            }
        }

        return array_values(array_keys($blocked));
    }

    /**
     * @return list<string>
     */
    protected function detectBlockedAttributes(string $html): array
    {
        $blocked = [];
        if ($html === '') {
            return [];
        }

        preg_match_all('/<([a-z0-9:-]+)([^>]*)>/i', $html, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $element = strtolower($match[1]);
            $attributesString = $match[2] ?? '';

            preg_match_all('/([a-z0-9:-]+)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', $attributesString, $attributeMatches, PREG_SET_ORDER);

            foreach ($attributeMatches as $attributeMatch) {
                $name = strtolower($attributeMatch[1]);

                if (str_starts_with($name, 'on')) {
                    $blocked[$name] = true;
                    continue;
                }

                $allowed = array_merge($this->allowedAttributes['*'] ?? [], $this->allowedAttributes[$element] ?? []);

                if (! in_array($name, $allowed, true)) {
                    $blocked[$name] = true;
                }
            }
        }

        return array_values(array_keys($blocked));
    }

    /**
     * @return list<string>
     */
    protected function detectBlockedProtocols(string $html): array
    {
        $protocols = [];
        if ($html === '') {
            return [];
        }

        preg_match_all('/<([a-z0-9:-]+)([^>]*)>/i', $html, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $attributesString = $match[2] ?? '';
            preg_match_all('/(href|src|xlink:href)\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $attributesString, $attributeMatches, PREG_SET_ORDER);

            foreach ($attributeMatches as $attributeMatch) {
                $value = $attributeMatch[3] ?? $attributeMatch[4] ?? $attributeMatch[5] ?? '';
                $value = trim($value);

                if ($value === '') {
                    continue;
                }

                $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));

                if ($scheme !== '' && ! in_array($scheme, $this->allowedSchemes, true)) {
                    $protocols[$scheme] = true;
                    continue;
                }

                if (str_starts_with(strtolower($value), 'data:') && ! in_array('data', $this->allowedSchemes, true)) {
                    $protocols['data'] = true;
                }

                if (str_starts_with(strtolower($value), 'javascript:')) {
                    $protocols['javascript'] = true;
                }
            }
        }

        return array_values(array_keys($protocols));
    }
}

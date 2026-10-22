<?php

declare(strict_types=1);

namespace WcagScout\Model;

use Stringable;

/**
 * Stable identity of a finding across runs, used by baseline and comparison.
 *
 * Built from rule, page and selector only. The element's HTML is deliberately left out:
 * it often contains values that change on every request (CSRF tokens, cache-busting
 * query strings, generated IDs), which would make every finding look new.
 */
final readonly class Fingerprint implements Stringable
{
    private function __construct(public string $value) {}

    public static function of(string $ruleId, Url $pageUrl, string $selector): self
    {
        $normalisedSelector = preg_replace('/\s+/', ' ', trim($selector)) ?? $selector;

        return new self(hash('sha256', $ruleId . "\0" . $pageUrl . "\0" . $normalisedSelector));
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}

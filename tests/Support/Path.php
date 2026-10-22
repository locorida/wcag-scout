<?php

declare(strict_types=1);

namespace WcagScout\Tests\Support;

use DOMElement;
use DOMXPath;
use PHPUnit\Framework\Assert;

/**
 * Typed lookups for assertions on decoded JSON and on XML/HTML documents.
 */
final class Path
{
    /** Value at a dotted path such as "pages.0.findings.1.severity"; fails the test if absent. */
    public static function json(mixed $data, string $path): mixed
    {
        foreach (explode('.', $path) as $key) {
            Assert::assertIsArray($data, "no array at {$key} of {$path}");
            Assert::assertArrayHasKey($key, $data, "missing {$key} of {$path}");
            $data = $data[$key];
        }

        return $data;
    }

    public static function string(DOMXPath $xpath, string $expression): string
    {
        $value = $xpath->evaluate('string(' . $expression . ')');
        Assert::assertIsString($value);

        return $value;
    }

    public static function count(DOMXPath $xpath, string $expression): int
    {
        $value = $xpath->evaluate('count(' . $expression . ')');
        Assert::assertIsFloat($value);

        return (int) $value;
    }

    /** @return list<DOMElement> */
    public static function elements(DOMXPath $xpath, string $expression): array
    {
        $nodes = $xpath->query($expression);
        Assert::assertNotFalse($nodes);

        $elements = [];
        foreach ($nodes as $node) {
            if ($node instanceof DOMElement) {
                $elements[] = $node;
            }
        }

        return $elements;
    }
}

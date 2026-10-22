<?php

declare(strict_types=1);

namespace WcagScout\Standards;

use InvalidArgumentException;

/**
 * All 86 success criteria of WCAG 2.2 (4.1.1 Parsing is obsolete and not included).
 * The mapping to EN 301 549 clauses lives in {@see En301549Version}.
 */
final class WcagCatalog
{
    /** @var array<string, array{Level, string}> number => [level, introduced in] */
    private const array CRITERIA = [
        '1.1.1' => [Level::A, '2.0'],
        '1.2.1' => [Level::A, '2.0'],
        '1.2.2' => [Level::A, '2.0'],
        '1.2.3' => [Level::A, '2.0'],
        '1.2.4' => [Level::AA, '2.0'],
        '1.2.5' => [Level::AA, '2.0'],
        '1.2.6' => [Level::AAA, '2.0'],
        '1.2.7' => [Level::AAA, '2.0'],
        '1.2.8' => [Level::AAA, '2.0'],
        '1.2.9' => [Level::AAA, '2.0'],
        '1.3.1' => [Level::A, '2.0'],
        '1.3.2' => [Level::A, '2.0'],
        '1.3.3' => [Level::A, '2.0'],
        '1.3.4' => [Level::AA, '2.1'],
        '1.3.5' => [Level::AA, '2.1'],
        '1.3.6' => [Level::AAA, '2.1'],
        '1.4.1' => [Level::A, '2.0'],
        '1.4.2' => [Level::A, '2.0'],
        '1.4.3' => [Level::AA, '2.0'],
        '1.4.4' => [Level::AA, '2.0'],
        '1.4.5' => [Level::AA, '2.0'],
        '1.4.6' => [Level::AAA, '2.0'],
        '1.4.7' => [Level::AAA, '2.0'],
        '1.4.8' => [Level::AAA, '2.0'],
        '1.4.9' => [Level::AAA, '2.0'],
        '1.4.10' => [Level::AA, '2.1'],
        '1.4.11' => [Level::AA, '2.1'],
        '1.4.12' => [Level::AA, '2.1'],
        '1.4.13' => [Level::AA, '2.1'],
        '2.1.1' => [Level::A, '2.0'],
        '2.1.2' => [Level::A, '2.0'],
        '2.1.3' => [Level::AAA, '2.0'],
        '2.1.4' => [Level::A, '2.1'],
        '2.2.1' => [Level::A, '2.0'],
        '2.2.2' => [Level::A, '2.0'],
        '2.2.3' => [Level::AAA, '2.0'],
        '2.2.4' => [Level::AAA, '2.0'],
        '2.2.5' => [Level::AAA, '2.0'],
        '2.2.6' => [Level::AAA, '2.1'],
        '2.3.1' => [Level::A, '2.0'],
        '2.3.2' => [Level::AAA, '2.0'],
        '2.3.3' => [Level::AAA, '2.1'],
        '2.4.1' => [Level::A, '2.0'],
        '2.4.2' => [Level::A, '2.0'],
        '2.4.3' => [Level::A, '2.0'],
        '2.4.4' => [Level::A, '2.0'],
        '2.4.5' => [Level::AA, '2.0'],
        '2.4.6' => [Level::AA, '2.0'],
        '2.4.7' => [Level::AA, '2.0'],
        '2.4.8' => [Level::AAA, '2.0'],
        '2.4.9' => [Level::AAA, '2.0'],
        '2.4.10' => [Level::AAA, '2.0'],
        '2.4.11' => [Level::AA, '2.2'],
        '2.4.12' => [Level::AAA, '2.2'],
        '2.4.13' => [Level::AAA, '2.2'],
        '2.5.1' => [Level::A, '2.1'],
        '2.5.2' => [Level::A, '2.1'],
        '2.5.3' => [Level::A, '2.1'],
        '2.5.4' => [Level::A, '2.1'],
        '2.5.5' => [Level::AAA, '2.1'],
        '2.5.6' => [Level::AAA, '2.1'],
        '2.5.7' => [Level::AA, '2.2'],
        '2.5.8' => [Level::AA, '2.2'],
        '3.1.1' => [Level::A, '2.0'],
        '3.1.2' => [Level::AA, '2.0'],
        '3.1.3' => [Level::AAA, '2.0'],
        '3.1.4' => [Level::AAA, '2.0'],
        '3.1.5' => [Level::AAA, '2.0'],
        '3.1.6' => [Level::AAA, '2.0'],
        '3.2.1' => [Level::A, '2.0'],
        '3.2.2' => [Level::A, '2.0'],
        '3.2.3' => [Level::AA, '2.0'],
        '3.2.4' => [Level::AA, '2.0'],
        '3.2.5' => [Level::AAA, '2.0'],
        '3.2.6' => [Level::A, '2.2'],
        '3.3.1' => [Level::A, '2.0'],
        '3.3.2' => [Level::A, '2.0'],
        '3.3.3' => [Level::AA, '2.0'],
        '3.3.4' => [Level::AA, '2.0'],
        '3.3.5' => [Level::AAA, '2.0'],
        '3.3.6' => [Level::AAA, '2.0'],
        '3.3.7' => [Level::A, '2.2'],
        '3.3.8' => [Level::AA, '2.2'],
        '3.3.9' => [Level::AAA, '2.2'],
        '4.1.2' => [Level::A, '2.0'],
        '4.1.3' => [Level::AA, '2.1'],
    ];

    /** @var array<string, SuccessCriterion> */
    private array $criteria = [];

    public function __construct()
    {
        foreach (self::CRITERIA as $number => [$level, $since]) {
            $this->criteria[$number] = new SuccessCriterion($number, $level, $since);
        }
    }

    public function get(string $number): SuccessCriterion
    {
        return $this->criteria[$number]
            ?? throw new InvalidArgumentException(\sprintf('Unknown WCAG 2.2 success criterion "%s".', $number));
    }

    public function has(string $number): bool
    {
        return isset($this->criteria[$number]);
    }

    /** @return list<SuccessCriterion> in document order */
    public function all(): array
    {
        return array_values($this->criteria);
    }

    /**
     * Extracts success criteria from axe-core rule tags such as "wcag143" or "wcag2411".
     * Level tags ("wcag2aa"), best-practice and EN tags are ignored.
     *
     * @param list<string> $tags
     * @return list<SuccessCriterion>
     */
    public function fromAxeTags(array $tags): array
    {
        $found = [];
        foreach ($tags as $tag) {
            if (preg_match('/^wcag(\d)(\d)(\d{1,2})$/', $tag, $m) !== 1) {
                continue;
            }
            $number = $m[1] . '.' . $m[2] . '.' . $m[3];
            // Tags for criteria outside WCAG 2.2 (e.g. the obsolete 4.1.1) are skipped.
            if ($this->has($number)) {
                $found[$number] = $this->criteria[$number];
            }
        }

        return array_values($found);
    }
}

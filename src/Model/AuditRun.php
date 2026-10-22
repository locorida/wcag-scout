<?php

declare(strict_types=1);

namespace WcagScout\Model;

use DateTimeImmutable;

/**
 * The complete result of one audit. Every report format is rendered from this object,
 * and the JSON report can be read back into it (for render, baseline and compare).
 */
final readonly class AuditRun
{
    /**
     * @param list<PageResult> $pages
     * @param array<string, string> $engines engine name => version, e.g. ["axe-core" => "4.14.0"]
     * @param array<string, scalar|list<string>|null> $options the effective options, for the record
     */
    public function __construct(
        public string $toolVersion,
        public DateTimeImmutable $startedAt,
        public DateTimeImmutable $finishedAt,
        public array $engines,
        public array $options,
        public array $pages,
    ) {}

    /** @return list<Finding> */
    public function findings(): array
    {
        return array_merge(...array_map(static fn(PageResult $p): array => $p->findings, $this->pages));
    }

    /** @return list<PageResult> */
    public function pagesWithStatus(PageStatus $status): array
    {
        return array_values(array_filter($this->pages, static fn(PageResult $p): bool => $p->status === $status));
    }

    /** @return array<string, int> every severity value, most severe first, including zeros */
    public function countBySeverity(): array
    {
        $counts = [];
        foreach (Severity::ordered() as $severity) {
            $counts[$severity->value] = 0;
        }
        foreach ($this->findings() as $finding) {
            $counts[$finding->severity->value] = ($counts[$finding->severity->value] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * Findings grouped by rule, groups ordered by severity (worst first), then by size.
     *
     * @return array<string, list<Finding>>
     */
    public function findingsByRule(): array
    {
        $groups = [];
        foreach ($this->findings() as $finding) {
            $groups[$finding->ruleId][] = $finding;
        }
        uasort($groups, static fn(array $a, array $b): int
            => [$b[0]->severity->rank(), \count($b)] <=> [$a[0]->severity->rank(), \count($a)]);

        return $groups;
    }

    public function durationSeconds(): int
    {
        return $this->finishedAt->getTimestamp() - $this->startedAt->getTimestamp();
    }

    /** @param list<PageResult> $pages */
    public function withPages(array $pages): self
    {
        return new self($this->toolVersion, $this->startedAt, $this->finishedAt, $this->engines, $this->options, $pages);
    }
}

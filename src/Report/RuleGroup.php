<?php

declare(strict_types=1);

namespace WcagScout\Report;

use WcagScout\Knowledge\RuleExplanation;
use WcagScout\Model\Finding;
use WcagScout\Model\Severity;
use WcagScout\Standards\SuccessCriterion;

/**
 * All findings of one rule, prepared for display.
 */
final readonly class RuleGroup
{
    /**
     * @param list<SuccessCriterion> $criteria
     * @param array<string, list<Finding>> $byPage page URL => findings
     */
    public function __construct(
        public string $id,
        public string $title,
        public ?RuleExplanation $explanation,
        public string $description,
        public string $helpUrl,
        public Severity $severity,
        public array $criteria,
        public int $count,
        public array $byPage,
    ) {}

    /** @return list<SuccessCriterion> criteria after the first, under which the group is listed */
    public function otherCriteria(): array
    {
        return \array_slice($this->criteria, 1);
    }

    public function pageCount(): int
    {
        return \count($this->byPage);
    }
}

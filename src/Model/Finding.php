<?php

declare(strict_types=1);

namespace WcagScout\Model;

use WcagScout\Standards\Level;
use WcagScout\Standards\SuccessCriterion;

/**
 * One rule violation on one element of one page.
 */
final readonly class Finding
{
    public Fingerprint $fingerprint;

    /**
     * @param string $engine the engine that reported it, e.g. "axe-core"
     * @param list<SuccessCriterion> $criteria empty for best-practice rules
     * @param string $summary the engine's description of what is wrong with this element
     * @param string|null $screenshot path of the element screenshot, relative to the report directory
     */
    public function __construct(
        public string $ruleId,
        public string $engine,
        public Severity $severity,
        public array $criteria,
        public Url $pageUrl,
        public string $selector,
        public string $html,
        public string $summary,
        public string $helpUrl,
        public ?string $screenshot = null,
    ) {
        $this->fingerprint = Fingerprint::of($ruleId, $pageUrl, $selector);
    }

    public function withScreenshot(string $path): self
    {
        return new self(
            $this->ruleId,
            $this->engine,
            $this->severity,
            $this->criteria,
            $this->pageUrl,
            $this->selector,
            $this->html,
            $this->summary,
            $this->helpUrl,
            $path,
        );
    }

    /** Best-practice rules improve accessibility but map to no WCAG success criterion. */
    public function isBestPractice(): bool
    {
        return $this->criteria === [];
    }

    /** Lowest WCAG level among the mapped criteria, or null for best-practice findings. */
    public function level(): ?Level
    {
        $levels = array_map(static fn(SuccessCriterion $c): Level => $c->level, $this->criteria);
        usort($levels, static fn(Level $a, Level $b): int => $a->rank() <=> $b->rank());

        return $levels[0] ?? null;
    }
}

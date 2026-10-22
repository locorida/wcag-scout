<?php

declare(strict_types=1);

namespace WcagScout\Standards;

final readonly class SuccessCriterion
{
    /**
     * @param string $number e.g. "1.4.3"
     * @param string $since WCAG version that introduced the criterion: "2.0", "2.1" or "2.2"
     */
    public function __construct(
        public string $number,
        public Level $level,
        public string $since,
    ) {}

    /** Key for translated titles, e.g. "wcag.1.4.3". */
    public function translationKey(): string
    {
        return 'wcag.' . $this->number;
    }

    public function en301549Clause(En301549Version $version = En301549Version::DEFAULT): ?string
    {
        return $version->clauseFor($this);
    }
}

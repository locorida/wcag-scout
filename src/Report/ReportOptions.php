<?php

declare(strict_types=1);

namespace WcagScout\Report;

use InvalidArgumentException;
use WcagScout\Engine\AuditOptions;
use WcagScout\Model\Finding;
use WcagScout\Model\Fingerprint;
use WcagScout\Model\Severity;
use WcagScout\Standards\En301549Version;
use WcagScout\Standards\Level;

final readonly class ReportOptions
{
    /**
     * @param array<string, true>|null $knownFingerprints findings from the baseline; null = no baseline
     * @param Severity|null $failOn lowest severity that fails a CI run; null = never fail
     */
    public function __construct(
        public string $language = 'en',
        public Level $level = Level::AA,
        public En301549Version $en301549 = En301549Version::DEFAULT,
        public ?array $knownFingerprints = null,
        public ?Severity $failOn = Severity::Minor,
    ) {
        if (!\in_array($language, AuditOptions::LANGUAGES, true)) {
            throw new InvalidArgumentException(\sprintf('Unsupported language "%s".', $language));
        }
    }

    public function hasBaseline(): bool
    {
        return $this->knownFingerprints !== null;
    }

    public function isKnown(Fingerprint $fingerprint): bool
    {
        return isset($this->knownFingerprints[(string) $fingerprint]);
    }

    /**
     * The single rule for "does this finding fail the build", shared by the exit code and
     * the JUnit report: a WCAG violation (not a best practice), at or above the threshold,
     * and not already in the baseline.
     */
    public function isFailing(Finding $finding): bool
    {
        return $this->failOn !== null
            && !$finding->isBestPractice()
            && $finding->severity->isAtLeast($this->failOn)
            && !$this->isKnown($finding->fingerprint);
    }
}

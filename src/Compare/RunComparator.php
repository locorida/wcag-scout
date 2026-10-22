<?php

declare(strict_types=1);

namespace WcagScout\Compare;

use WcagScout\Model\AuditRun;
use WcagScout\Model\Finding;
use WcagScout\Model\PageStatus;

/**
 * Compares two runs of the same site by finding fingerprints.
 *
 * A finding only counts as fixed if its page was audited again: a page that hit the page
 * limit or timed out in the later run says nothing about whether its problems are gone.
 */
final readonly class RunComparator
{
    public function compare(AuditRun $before, AuditRun $after): Comparison
    {
        $afterByFingerprint = self::index($after->findings());
        $beforeByFingerprint = self::index($before->findings());

        $auditedAfter = [];
        foreach ($after->pagesWithStatus(PageStatus::Audited) as $page) {
            $auditedAfter[(string) $page->url] = true;
        }

        $fixed = [];
        $notRechecked = [];
        foreach ($beforeByFingerprint as $fingerprint => $finding) {
            if (isset($afterByFingerprint[$fingerprint])) {
                continue;
            }
            if (isset($auditedAfter[(string) $finding->pageUrl])) {
                $fixed[] = $finding;
            } else {
                $notRechecked[] = $finding;
            }
        }

        $added = [];
        $unchanged = [];
        foreach ($afterByFingerprint as $fingerprint => $finding) {
            if (isset($beforeByFingerprint[$fingerprint])) {
                $unchanged[] = $finding;
            } else {
                $added[] = $finding;
            }
        }

        return new Comparison($before, $after, $fixed, $added, $unchanged, $notRechecked);
    }

    /**
     * @param list<Finding> $findings
     * @return array<string, Finding>
     */
    private static function index(array $findings): array
    {
        $index = [];
        foreach ($findings as $finding) {
            $index[(string) $finding->fingerprint] ??= $finding;
        }

        return $index;
    }
}

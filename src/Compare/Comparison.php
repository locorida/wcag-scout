<?php

declare(strict_types=1);

namespace WcagScout\Compare;

use WcagScout\Model\AuditRun;
use WcagScout\Model\Finding;

final readonly class Comparison
{
    /**
     * @param list<Finding> $fixed in the earlier run, gone in the later one, and the page was audited again
     * @param list<Finding> $added only in the later run
     * @param list<Finding> $unchanged in both runs (as reported by the later run)
     * @param list<Finding> $notRechecked in the earlier run, but its page was not audited in the later one
     */
    public function __construct(
        public AuditRun $before,
        public AuditRun $after,
        public array $fixed,
        public array $added,
        public array $unchanged,
        public array $notRechecked,
    ) {}
}

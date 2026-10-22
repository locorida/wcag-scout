<?php

declare(strict_types=1);

namespace WcagScout\Engine\Axe;

use WcagScout\Model\Finding;
use WcagScout\Model\RuleInfo;

final readonly class MappedResult
{
    /**
     * @param list<Finding> $findings
     * @param array<string, RuleInfo> $rules
     */
    public function __construct(
        public array $findings,
        public array $rules,
    ) {}
}

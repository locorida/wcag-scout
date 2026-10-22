<?php

declare(strict_types=1);

namespace WcagScout\Knowledge;

final readonly class RuleExplanation
{
    public function __construct(
        public string $title,
        public string $why,
        public string $who,
        public string $fix,
        public ?string $example = null,
    ) {}
}

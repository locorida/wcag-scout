<?php

declare(strict_types=1);

namespace WcagScout\Model;

/**
 * What an engine says about one of its rules, in the language of the run.
 */
final readonly class RuleInfo
{
    /** @param list<string> $tags the engine's own tags, kept for the record */
    public function __construct(
        public string $id,
        public string $engine,
        /** Short title, e.g. "Images must have alternative text". */
        public string $help,
        public string $description,
        public string $helpUrl,
        public array $tags = [],
    ) {}
}

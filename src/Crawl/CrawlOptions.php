<?php

declare(strict_types=1);

namespace WcagScout\Crawl;

use InvalidArgumentException;

final readonly class CrawlOptions
{
    public function __construct(
        /** Pages to load at most (skipped pages do not count). */
        public int $maxPages = 50,
        /** Minimum pause between two requests to the same site, in seconds. */
        public float $delaySeconds = 1.0,
        /** Also audit same-site links found on audited pages. */
        public bool $followLinks = false,
        /** How many link hops away from the given URLs to go when following links. */
        public int $maxDepth = 2,
    ) {
        if ($maxPages < 1) {
            throw new InvalidArgumentException('The page limit must be at least 1.');
        }
        if ($delaySeconds < 0.0) {
            throw new InvalidArgumentException('The delay cannot be negative.');
        }
        if ($maxDepth < 0) {
            throw new InvalidArgumentException('The link depth cannot be negative.');
        }
    }
}

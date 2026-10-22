<?php

declare(strict_types=1);

namespace WcagScout\Crawl;

use WcagScout\Model\PageResult;
use WcagScout\Model\Url;

/**
 * Mutable bookkeeping of one crawl. Internal to {@see Crawler}.
 *
 * @internal
 */
final class CrawlState
{
    /** @var list<PageResult> */
    public private(set) array $results = [];

    public private(set) int $loaded = 0;

    /** @var array<string, true> */
    private array $seen = [];

    /** @var array<string, true> */
    private array $origins = [];

    /** @var list<array{Url, int}> */
    private array $queue = [];

    /** @param list<string> $scopeOrigins */
    public function __construct(array $scopeOrigins)
    {
        foreach ($scopeOrigins as $origin) {
            $this->origins[Url::parse($origin)->origin()] = true;
        }
    }

    /** @return bool false if the URL was seen before */
    public function markSeen(Url $url): bool
    {
        if (isset($this->seen[(string) $url])) {
            return false;
        }
        $this->seen[(string) $url] = true;

        return true;
    }

    public function inScope(Url $url): bool
    {
        // Without an explicit scope, the first URL defines the site.
        if ($this->origins === []) {
            $this->origins[$url->origin()] = true;
        }

        return isset($this->origins[$url->origin()]);
    }

    public function record(PageResult $result, bool $loaded): void
    {
        $this->results[] = $result;
        if ($loaded) {
            ++$this->loaded;
        }
    }

    public function enqueue(Url $url, int $depth): void
    {
        if (!isset($this->seen[(string) $url])) {
            $this->queue[] = [$url, $depth];
        }
    }

    /** @return array{Url, int}|null */
    public function dequeue(): ?array
    {
        return array_shift($this->queue);
    }
}

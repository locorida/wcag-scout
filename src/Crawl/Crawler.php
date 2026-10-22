<?php

declare(strict_types=1);

namespace WcagScout\Crawl;

use RuntimeException;
use WcagScout\Input\UrlSource;
use WcagScout\Model\PageResult;
use WcagScout\Model\Url;

/**
 * Decides which pages are visited, in which order and how fast.
 *
 * - Only the sites (origins) of the start URLs are crawled.
 * - robots.txt is honoured, including a crawl-delay longer than the configured pause.
 * - Given URLs come first, in their order; with --follow-links, discovered links follow
 *   breadth-first up to the configured depth.
 * - Every URL is visited at most once, and at most maxPages pages are loaded.
 */
final readonly class Crawler
{
    public function __construct(
        private RobotsPolicy $robots,
        private Throttle $throttle,
        private PageAuditor $auditor,
        private CrawlOptions $options,
        private ?CrawlObserver $observer = null,
    ) {}

    /**
     * @param list<string> $scopeOrigins sites that may be crawled; empty = the origin of the first URL
     * @return list<PageResult>
     */
    public function crawl(UrlSource $source, array $scopeOrigins = []): array
    {
        $state = new CrawlState($scopeOrigins);

        foreach ($source->urls() as $url) {
            if ($state->loaded >= $this->options->maxPages) {
                break;
            }
            $this->visit($url, 0, $state);
        }

        while ($state->loaded < $this->options->maxPages && ($next = $state->dequeue()) !== null) {
            $this->visit($next[0], $next[1], $state);
        }

        return $state->results;
    }

    private function skip(CrawlState $state, PageResult $result): void
    {
        $state->record($result, loaded: false);
        $this->observer?->pageFinished($result);
    }

    private function visit(Url $url, int $depth, CrawlState $state): void
    {
        if (!$state->markSeen($url)) {
            return;
        }
        if (!$state->inScope($url)) {
            $this->skip($state, PageResult::skipped($url, PageResult::SKIPPED_OUT_OF_SCOPE));

            return;
        }
        if (!$this->robots->isAllowed($url)) {
            $this->skip($state, PageResult::skipped($url, PageResult::SKIPPED_ROBOTS_TXT));

            return;
        }

        $crawlDelay = $this->robots->crawlDelay($url);
        if ($crawlDelay !== null) {
            $this->throttle->requireDelay($url, $crawlDelay);
        }
        $this->throttle->wait($url);

        $this->observer?->pageStarted($url, $state->loaded + 1, $this->options->maxPages);
        try {
            $visit = $this->auditor->audit($url);
        } catch (RuntimeException $e) {
            $visit = new PageVisit(PageResult::failed($url, $e->getMessage()));
        }
        $state->record($visit->result, loaded: true);
        $this->observer?->pageFinished($visit->result);

        if ($this->options->followLinks && $depth < $this->options->maxDepth) {
            foreach ($visit->links as $link) {
                // Out-of-scope links are dropped silently: they are not part of the audit.
                if ($state->inScope($link)) {
                    $state->enqueue($link, $depth + 1);
                }
            }
        }
    }
}

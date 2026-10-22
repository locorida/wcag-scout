<?php

declare(strict_types=1);

namespace WcagScout\Audit;

use InvalidArgumentException;
use WcagScout\Browser\BrowserException;
use WcagScout\Browser\BrowserPage;
use WcagScout\Browser\PageLoader;
use WcagScout\Crawl\PageAuditor;
use WcagScout\Crawl\PageVisit;
use WcagScout\Engine\AuditEngine;
use WcagScout\Model\PageResult;
use WcagScout\Model\Url;

/**
 * Loads a page in the browser and runs every engine on it.
 */
final readonly class BrowserPageAuditor implements PageAuditor
{
    /** @param list<AuditEngine> $engines */
    public function __construct(
        private PageLoader $browser,
        private array $engines,
        private ?ScreenshotWriter $screenshots = null,
        private bool $collectLinks = false,
    ) {}

    public function audit(Url $url): PageVisit
    {
        $start = hrtime(true);
        try {
            $page = $this->browser->open($url);
        } catch (BrowserException $e) {
            return new PageVisit(PageResult::failed($url, $e->getMessage(), null, self::elapsedMs($start)));
        }

        try {
            return $this->auditLoaded($page, $url, $start);
        } catch (BrowserException $e) {
            return new PageVisit(PageResult::failed($url, $e->getMessage(), $page->info()->httpStatus, self::elapsedMs($start)));
        } finally {
            $page->close();
        }
    }

    private function auditLoaded(BrowserPage $page, Url $url, int $start): PageVisit
    {
        $info = $page->info();

        // A redirect to another site leaves the audit's scope.
        try {
            $finalUrl = Url::parse($info->finalUrl);
        } catch (InvalidArgumentException) {
            $finalUrl = $url;
        }
        if (!$finalUrl->isSameOrigin($url)) {
            return new PageVisit(PageResult::skipped($url, PageResult::SKIPPED_OUT_OF_SCOPE));
        }
        if ($info->httpStatus !== null && $info->httpStatus >= 400) {
            return new PageVisit(PageResult::failed($url, 'HTTP ' . $info->httpStatus, $info->httpStatus, self::elapsedMs($start)));
        }
        if (!$info->isHtml()) {
            return new PageVisit(PageResult::skipped($url, PageResult::SKIPPED_NOT_HTML));
        }

        $findings = [];
        foreach ($this->engines as $engine) {
            $findings = [...$findings, ...$engine->run($page, $url)];
        }
        if ($this->screenshots !== null && $findings !== []) {
            $findings = $this->screenshots->capture($page, $findings);
        }

        $links = [];
        if ($this->collectLinks) {
            foreach ($page->links() as $link) {
                try {
                    $links[] = Url::parse($link);
                } catch (InvalidArgumentException) {
                    // Not an http(s) link.
                }
            }
        }

        return new PageVisit(
            PageResult::audited($url, $info->title, $info->httpStatus, $findings, self::elapsedMs($start)),
            $links,
        );
    }

    private static function elapsedMs(int $start): int
    {
        return intdiv(hrtime(true) - $start, 1_000_000);
    }
}

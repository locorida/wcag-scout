<?php

declare(strict_types=1);

namespace WcagScout\Crawl;

use WcagScout\Model\PageResult;
use WcagScout\Model\Url;

/**
 * Progress callbacks, e.g. for the console progress output.
 */
interface CrawlObserver
{
    public function pageStarted(Url $url, int $number, int $limit): void;

    /** Called for every recorded page; skipped pages get no pageStarted() before. */
    public function pageFinished(PageResult $result): void;
}

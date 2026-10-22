<?php

declare(strict_types=1);

namespace WcagScout\Crawl;

use WcagScout\Model\PageResult;
use WcagScout\Model\Url;

final readonly class PageVisit
{
    /** @param list<Url> $links links found on the page, for --follow-links */
    public function __construct(
        public PageResult $result,
        public array $links = [],
    ) {}
}

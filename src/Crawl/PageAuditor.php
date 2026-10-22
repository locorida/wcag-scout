<?php

declare(strict_types=1);

namespace WcagScout\Crawl;

use WcagScout\Model\Url;

/**
 * Loads one page and checks it. The crawler decides *which* pages to visit and *when*;
 * implementations decide *how* (headless browser + axe-core, or a fake in tests).
 */
interface PageAuditor
{
    public function audit(Url $url): PageVisit;
}

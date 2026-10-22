<?php

declare(strict_types=1);

namespace WcagScout\Browser;

use WcagScout\Model\Url;

interface PageLoader
{
    /** @throws BrowserException */
    public function open(Url $url): BrowserPage;
}

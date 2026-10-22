<?php

declare(strict_types=1);

namespace WcagScout\Tests\Support;

use WcagScout\Browser\BrowserException;
use WcagScout\Browser\BrowserPage;
use WcagScout\Browser\PageLoader;
use WcagScout\Model\Url;

final class FakeLoader implements PageLoader
{
    public function __construct(private readonly ?BrowserPage $page) {}

    public function open(Url $url): BrowserPage
    {
        return $this->page ?? throw new BrowserException('net::ERR_CONNECTION_REFUSED');
    }
}

<?php

declare(strict_types=1);

namespace WcagScout\Input;

use WcagScout\Model\Url;

/**
 * Where the URLs to audit come from. Sources are lazy: the crawler stops pulling once it
 * reaches its page limit, so a huge sitemap is never read completely.
 */
interface UrlSource
{
    /** @return iterable<Url> */
    public function urls(): iterable;
}

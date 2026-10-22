<?php

declare(strict_types=1);

namespace WcagScout\Crawl;

use WcagScout\Cli\Application;

/**
 * How wcag-scout identifies itself. Site operators can find the contact URL in their logs
 * and address the crawler in robots.txt by its product token.
 */
final readonly class UserAgent
{
    public const string PRODUCT_TOKEN = 'wcag-scout';
    public const string DEFAULT_CONTACT = 'https://github.com/locorida/wcag-scout';

    public function __construct(
        public string $contactUrl = self::DEFAULT_CONTACT,
        public string $version = Application::VERSION,
    ) {}

    /** For plain HTTP requests (robots.txt, sitemaps). */
    public function forHttp(): string
    {
        return \sprintf('%s/%s (+%s)', self::PRODUCT_TOKEN, $this->version, $this->contactUrl);
    }

    /**
     * For the browser: Chromium's own string with ours appended. Keeping the browser part
     * means sites serve the same markup a regular Chrome user would get, which is what an
     * accessibility audit has to look at.
     */
    public function forBrowser(string $browserUserAgent): string
    {
        $browserUserAgent = str_replace('HeadlessChrome', 'Chrome', $browserUserAgent);

        return $browserUserAgent . ' ' . $this->forHttp();
    }
}

<?php

declare(strict_types=1);

namespace WcagScout\Crawl;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use WcagScout\Model\Url;

/**
 * Fetches and caches robots.txt per origin and answers whether a URL may be crawled.
 */
final class RobotsPolicy
{
    /** @var array<string, RobotsTxt> origin => parsed file */
    private array $cache = [];

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly UserAgent $userAgent,
        private readonly Throttle $throttle,
    ) {}

    public function isAllowed(Url $url): bool
    {
        return $this->for($url)->isAllowed($url->pathAndQuery(), UserAgent::PRODUCT_TOKEN);
    }

    public function crawlDelay(Url $url): ?float
    {
        return $this->for($url)->crawlDelay(UserAgent::PRODUCT_TOKEN);
    }

    /** @return list<string> sitemap URLs announced in robots.txt */
    public function sitemaps(Url $url): array
    {
        return $this->for($url)->sitemaps;
    }

    private function for(Url $url): RobotsTxt
    {
        return $this->cache[$url->origin()] ??= $this->fetch(Url::parse($url->origin() . '/robots.txt'));
    }

    /** Status handling per RFC 9309, section 2.3.1. */
    private function fetch(Url $robotsUrl): RobotsTxt
    {
        $this->throttle->wait($robotsUrl);
        try {
            $response = $this->http->request('GET', (string) $robotsUrl, [
                'headers' => ['User-Agent' => $this->userAgent->forHttp()],
                'max_redirects' => 5,
                'timeout' => 15,
            ]);
            $status = $response->getStatusCode();
            if ($status >= 200 && $status < 300) {
                return RobotsTxt::parse($response->getContent());
            }
            if ($status >= 400 && $status < 500) {
                return RobotsTxt::allowAll();
            }

            return RobotsTxt::disallowAll();
        } catch (ExceptionInterface) {
            return RobotsTxt::disallowAll();
        }
    }
}

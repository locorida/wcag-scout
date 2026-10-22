<?php

declare(strict_types=1);

namespace WcagScout\Input;

use InvalidArgumentException;
use RuntimeException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use WcagScout\Crawl\Throttle;
use WcagScout\Crawl\UserAgent;
use WcagScout\Model\Url;

/**
 * Page URLs from a sitemap or sitemap index, read lazily.
 *
 * Sitemap indexes may not nest according to the protocol; nested indexes are followed
 * anyway, up to MAX_DEPTH levels, and each sitemap file is fetched at most once.
 */
final class SitemapSource implements UrlSource
{
    private const int MAX_DEPTH = 3;

    /** @var list<string> problems with individual files; the source keeps going */
    private array $warnings = [];

    public function __construct(
        private readonly Url $sitemapUrl,
        private readonly HttpClientInterface $http,
        private readonly UserAgent $userAgent,
        private readonly Throttle $throttle,
    ) {}

    public function urls(): iterable
    {
        $seen = [];
        yield from $this->read($this->sitemapUrl, 0, $seen);
    }

    /** @return list<string> */
    public function warnings(): array
    {
        return $this->warnings;
    }

    /**
     * @param array<string, true> $seen
     * @return iterable<Url>
     */
    private function read(Url $url, int $depth, array &$seen): iterable
    {
        if (isset($seen[(string) $url])) {
            return;
        }
        $seen[(string) $url] = true;

        $sitemap = $this->fetch($url);
        if ($sitemap === null) {
            return;
        }

        foreach ($sitemap->locations as $location) {
            try {
                $locationUrl = $url->resolve($location);
            } catch (InvalidArgumentException) {
                $this->warnings[] = \sprintf('%s: ignored invalid URL "%s".', $url, $location);
                continue;
            }

            if (!$sitemap->isIndex) {
                yield $locationUrl;
            } elseif ($depth + 1 < self::MAX_DEPTH) {
                yield from $this->read($locationUrl, $depth + 1, $seen);
            } else {
                $this->warnings[] = \sprintf('%s: sitemap indexes nested too deeply, skipped %s.', $url, $locationUrl);
            }
        }
    }

    private function fetch(Url $url): ?Sitemap
    {
        $this->throttle->wait($url);
        try {
            $response = $this->http->request('GET', (string) $url, [
                'headers' => ['User-Agent' => $this->userAgent->forHttp()],
                'max_redirects' => 5,
                'timeout' => 30,
                'max_duration' => 120,
            ]);
            // A Content-Encoding is decoded by the client; a .xml.gz file served as plain bytes
            // is recognised by Sitemap::parse().
            return Sitemap::parse($response->getContent());
        } catch (ExceptionInterface|RuntimeException $e) {
            if ($url->equals($this->sitemapUrl)) {
                throw new RuntimeException(\sprintf('Cannot read sitemap %s: %s', $url, $e->getMessage()), 0, $e);
            }
            $this->warnings[] = \sprintf('%s: %s', $url, $e->getMessage());

            return null;
        }
    }
}

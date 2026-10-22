<?php

declare(strict_types=1);

namespace WcagScout\Tests\Unit\Input;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use WcagScout\Crawl\Throttle;
use WcagScout\Crawl\UserAgent;
use WcagScout\Input\SitemapSource;
use WcagScout\Model\Url;

final class SitemapSourceTest extends TestCase
{
    /** @var list<string> */
    private array $requested = [];

    public function testFollowsIndexAndKeepsGoingWhenOneFileFails(): void
    {
        $source = $this->source([
            'https://example.com/sitemap.xml' => self::index(['/pages.xml', '/broken.xml', '/missing.xml', '/pages.xml']),
            'https://example.com/pages.xml' => self::urlset(['/', '/about']),
            'https://example.com/broken.xml' => '<html>oops</html>',
        ]);

        $urls = array_map(strval(...), iterator_to_array($source->urls(), false));

        self::assertSame(['https://example.com/', 'https://example.com/about'], $urls);
        self::assertCount(2, $source->warnings());
        self::assertSame(
            ['https://example.com/sitemap.xml', 'https://example.com/pages.xml', 'https://example.com/broken.xml', 'https://example.com/missing.xml'],
            $this->requested,
            'each file is fetched once',
        );
    }

    public function testIsLazy(): void
    {
        $source = $this->source([
            'https://example.com/sitemap.xml' => self::index(['/a.xml', '/b.xml']),
            'https://example.com/a.xml' => self::urlset(['/1', '/2']),
            'https://example.com/b.xml' => self::urlset(['/3']),
        ]);

        foreach ($source->urls() as $url) {
            break;
        }

        self::assertNotContains('https://example.com/b.xml', $this->requested);
    }

    public function testFailsWhenTheGivenSitemapCannotBeRead(): void
    {
        $source = $this->source([]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot read sitemap https://example.com/sitemap.xml');
        iterator_to_array($source->urls());
    }

    /** @param array<string, string> $files */
    private function source(array $files): SitemapSource
    {
        $http = new MockHttpClient(function (string $method, string $url) use ($files): MockResponse {
            $this->requested[] = $url;

            return isset($files[$url]) ? new MockResponse($files[$url]) : new MockResponse('', ['http_code' => 404]);
        });

        return new SitemapSource(Url::parse('https://example.com/sitemap.xml'), $http, new UserAgent(), new Throttle(0.0, new MockClock()));
    }

    /** @param list<string> $paths */
    private static function index(array $paths): string
    {
        return '<sitemapindex>' . implode('', array_map(static fn(string $p): string => "<sitemap><loc>https://example.com{$p}</loc></sitemap>", $paths)) . '</sitemapindex>';
    }

    /** @param list<string> $paths */
    private static function urlset(array $paths): string
    {
        return '<urlset>' . implode('', array_map(static fn(string $p): string => "<url><loc>https://example.com{$p}</loc></url>", $paths)) . '</urlset>';
    }
}

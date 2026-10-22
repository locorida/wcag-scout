<?php

declare(strict_types=1);

namespace WcagScout\Tests\Unit\Crawl;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WcagScout\Crawl\RobotsTxt;

final class RobotsTxtTest extends TestCase
{
    /** @return iterable<string, array{string, string, bool}> robots.txt, path, expected */
    public static function cases(): iterable
    {
        yield 'empty file allows everything' => ['', '/any', true];
        yield 'disallow all for everyone' => ["User-agent: *\nDisallow: /", '/page', false];
        yield 'empty disallow allows' => ["User-agent: *\nDisallow:", '/page', true];
        yield 'prefix match' => ["User-agent: *\nDisallow: /private", '/private/x', false];
        yield 'prefix match is not a word match' => ["User-agent: *\nDisallow: /private", '/privateer', false];
        yield 'other paths allowed' => ["User-agent: *\nDisallow: /private", '/public', true];
        yield 'longest match wins (allow)' => ["User-agent: *\nDisallow: /a\nAllow: /a/b", '/a/b/c', true];
        yield 'longest match wins (disallow)' => ["User-agent: *\nAllow: /a\nDisallow: /a/b", '/a/b/c', false];
        yield 'tie goes to allow' => ["User-agent: *\nDisallow: /a\nAllow: /a", '/a', true];
        yield 'wildcard' => ["User-agent: *\nDisallow: /*.pdf", '/files/report.pdf', false];
        yield 'wildcard in the middle' => ["User-agent: *\nDisallow: /a/*/edit", '/a/42/edit', false];
        yield 'end anchor matches' => ["User-agent: *\nDisallow: /*.pdf\$", '/x.pdf', false];
        yield 'end anchor does not match longer path' => ["User-agent: *\nDisallow: /*.pdf\$", '/x.pdf?download=1', true];
        yield 'query string is matched' => ["User-agent: *\nDisallow: /*?session=", '/shop?session=1', false];
        yield 'specific group beats *' => ["User-agent: *\nDisallow: /\n\nUser-agent: wcag-scout\nAllow: /", '/page', true];
        yield 'specific group: * rules no longer apply' => ["User-agent: *\nDisallow: /a\n\nUser-agent: wcag-scout\nDisallow: /b", '/a', true];
        yield 'user-agent is case-insensitive' => ["User-agent: WCAG-Scout\nDisallow: /", '/page', false];
        yield 'user-agent with version' => ["User-agent: wcag-scout/1.0\nDisallow: /", '/page', false];
        yield 'groups for other bots ignored' => ["User-agent: OtherBot\nDisallow: /", '/page', true];
        yield 'several agents share a group' => ["User-agent: OtherBot\nUser-agent: wcag-scout\nDisallow: /x", '/x', false];
        yield 'matching groups are combined' => ["User-agent: wcag-scout\nDisallow: /a\n\nUser-agent: wcag-scout\nDisallow: /b", '/b', false];
        yield 'comments and blank lines' => ["# hello\nUser-agent: * # all\n\nDisallow: /x # no", '/x', false];
        yield 'CRLF line endings' => ["User-agent: *\r\nDisallow: /x\r\n", '/x', false];
        yield 'BOM' => ["\xEF\xBB\xBFUser-agent: *\nDisallow: /x", '/x', false];
        yield 'rules before any user-agent are ignored' => ["Disallow: /\nUser-agent: *\nAllow: /", '/page', true];
        yield 'robots.txt is always allowed' => ["User-agent: *\nDisallow: /", '/robots.txt', true];
        yield 'percent-encoded unreserved chars match literal' => ["User-agent: *\nDisallow: /%7Euser", '/~user/x', false];
        yield 'literal matches percent-encoded path' => ["User-agent: *\nDisallow: /~user", '/%7euser/x', false];
        yield 'regex characters are literal' => ["User-agent: *\nDisallow: /a.b", '/axb', true];
    }

    #[DataProvider('cases')]
    public function testDecidesWhetherPathIsAllowed(string $robots, string $path, bool $expected): void
    {
        self::assertSame($expected, RobotsTxt::parse($robots)->isAllowed($path, 'wcag-scout'));
    }

    public function testCrawlDelayAndSitemaps(): void
    {
        $robots = RobotsTxt::parse(<<<'TXT'
            Sitemap: https://example.com/sitemap.xml
            User-agent: *
            Crawl-delay: 5
            Disallow: /x

            User-agent: OtherBot
            Crawl-delay: 30
            Sitemap: https://example.com/news.xml
            TXT);

        self::assertSame(5.0, $robots->crawlDelay('wcag-scout'));
        self::assertSame(['https://example.com/sitemap.xml', 'https://example.com/news.xml'], $robots->sitemaps);
        self::assertNull(RobotsTxt::parse("User-agent: *\nDisallow: /")->crawlDelay('wcag-scout'));
        self::assertNull(RobotsTxt::parse("User-agent: *\nCrawl-delay: soon")->crawlDelay('wcag-scout'));
    }

    public function testFactoriesForMissingAndUnreachableFiles(): void
    {
        self::assertTrue(RobotsTxt::allowAll()->isAllowed('/anything', 'wcag-scout'));
        self::assertFalse(RobotsTxt::disallowAll()->isAllowed('/anything', 'wcag-scout'));
        self::assertTrue(RobotsTxt::disallowAll()->isAllowed('/robots.txt', 'wcag-scout'));
    }

    public function testIgnoresContentBeyondTheSizeLimit(): void
    {
        $robots = "User-agent: *\n" . str_repeat("# padding\n", intdiv(RobotsTxt::MAX_BYTES, 10)) . "Disallow: /late\n";

        self::assertTrue(RobotsTxt::parse($robots)->isAllowed('/late', 'wcag-scout'));
    }
}

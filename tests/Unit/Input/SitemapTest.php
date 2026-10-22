<?php

declare(strict_types=1);

namespace WcagScout\Tests\Unit\Input;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WcagScout\Input\Sitemap;

final class SitemapTest extends TestCase
{
    public function testParsesUrlset(): void
    {
        $sitemap = Sitemap::parse(<<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
              <url><loc>https://example.com/</loc><lastmod>2026-01-01</lastmod></url>
              <url><loc>
                https://example.com/a?x=1&amp;y=2
              </loc></url>
              <url><loc></loc></url>
            </urlset>
            XML);

        self::assertFalse($sitemap->isIndex);
        self::assertSame(['https://example.com/', 'https://example.com/a?x=1&y=2'], $sitemap->locations);
    }

    public function testParsesIndex(): void
    {
        $sitemap = Sitemap::parse(<<<'XML'
            <sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
              <sitemap><loc>https://example.com/pages.xml</loc></sitemap>
              <sitemap><loc>https://example.com/posts.xml.gz</loc></sitemap>
            </sitemapindex>
            XML);

        self::assertTrue($sitemap->isIndex);
        self::assertSame(['https://example.com/pages.xml', 'https://example.com/posts.xml.gz'], $sitemap->locations);
    }

    public function testParsesGzippedSitemap(): void
    {
        $xml = '<urlset><url><loc>https://example.com/</loc></url></urlset>';

        self::assertSame(['https://example.com/'], Sitemap::parse((string) gzencode($xml))->locations);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidSitemaps(): iterable
    {
        yield 'HTML page' => ['<!doctype html><html><body>Not found</body></html>'];
        yield 'broken XML' => ['<urlset><url><loc>https://example.com/</url>'];
        yield 'other XML' => ['<rss><channel><link>https://example.com/</link></channel></rss>'];
        yield 'empty' => [''];
        yield 'broken gzip' => ["\x1f\x8bnot really gzip"];
    }

    #[DataProvider('invalidSitemaps')]
    public function testRejectsInvalidInput(string $content): void
    {
        $this->expectException(RuntimeException::class);
        Sitemap::parse($content);
    }

    public function testDoesNotResolveExternalEntities(): void
    {
        $secret = tempnam(sys_get_temp_dir(), 'xxe');
        self::assertIsString($secret);
        file_put_contents($secret, 'https://attacker.example/leaked');

        try {
            $xml = '<?xml version="1.0"?><!DOCTYPE urlset [<!ENTITY xxe SYSTEM "file://' . $secret . '">]>'
                . '<urlset><url><loc>&xxe;</loc></url></urlset>';
            $locations = Sitemap::parse($xml)->locations;
        } catch (RuntimeException) {
            $locations = [];
        } finally {
            unlink($secret);
        }

        self::assertNotContains('https://attacker.example/leaked', $locations);
    }

    public function testRejectsDecompressionBomb(): void
    {
        // 60 MB of zeros compress to about 60 KB. Built in chunks to keep the test's own memory low.
        $deflate = deflate_init(ZLIB_ENCODING_GZIP, ['level' => 9]);
        self::assertNotFalse($deflate);
        $chunk = str_repeat("\0", 1024 * 1024);
        $bomb = '';
        for ($i = 0; $i < 60; ++$i) {
            $bomb .= (string) deflate_add($deflate, $chunk, ZLIB_NO_FLUSH);
        }
        $bomb .= (string) deflate_add($deflate, '', ZLIB_FINISH);

        $this->expectException(RuntimeException::class);
        Sitemap::parse($bomb);
    }
}

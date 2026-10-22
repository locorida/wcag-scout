<?php

declare(strict_types=1);

namespace WcagScout\Tests\Unit\Model;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WcagScout\Model\Url;

final class UrlTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function normalisationCases(): iterable
    {
        yield 'lower-cases scheme and host' => ['HTTPS://Example.COM/Path', 'https://example.com/Path'];
        yield 'adds root path' => ['https://example.com', 'https://example.com/'];
        yield 'drops default http port' => ['http://example.com:80/a', 'http://example.com/a'];
        yield 'drops default https port' => ['https://example.com:443/a', 'https://example.com/a'];
        yield 'keeps other ports' => ['http://localhost:8000/a', 'http://localhost:8000/a'];
        yield 'drops fragment' => ['https://example.com/a#section', 'https://example.com/a'];
        yield 'keeps query verbatim' => ['https://example.com/a?b=2&a=1', 'https://example.com/a?b=2&a=1'];
        yield 'resolves dot segments' => ['https://example.com/a/./b/../c', 'https://example.com/a/c'];
        yield 'cannot climb above root' => ['https://example.com/../../a', 'https://example.com/a'];
        yield 'trailing ".." keeps directory slash' => ['https://example.com/a/b/..', 'https://example.com/a/'];
        yield 'dots inside a segment are kept' => ['https://example.com/file..txt', 'https://example.com/file..txt'];
    }

    #[DataProvider('normalisationCases')]
    public function testNormalises(string $input, string $expected): void
    {
        self::assertSame($expected, (string) Url::parse($input));
    }

    /** @return iterable<string, array{string}> */
    public static function invalidUrls(): iterable
    {
        yield 'relative' => ['/path'];
        yield 'mailto' => ['mailto:someone@example.com'];
        yield 'javascript' => ['javascript:alert(1)'];
        yield 'ftp' => ['ftp://example.com/'];
        yield 'garbage' => ['http:///'];
    }

    #[DataProvider('invalidUrls')]
    public function testRejectsNonHttpUrls(string $input): void
    {
        $this->expectException(InvalidArgumentException::class);
        Url::parse($input);
    }

    /**
     * Examples from RFC 3986, section 5.4.1, against base http://a/b/c/d;p?q.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function rfc3986Resolution(): iterable
    {
        yield 'g' => ['g', 'http://a/b/c/g'];
        yield './g' => ['./g', 'http://a/b/c/g'];
        yield 'g/' => ['g/', 'http://a/b/c/g/'];
        yield '/g' => ['/g', 'http://a/g'];
        yield '//g' => ['//g', 'http://g/'];
        yield '?y' => ['?y', 'http://a/b/c/d;p?y'];
        yield 'g?y' => ['g?y', 'http://a/b/c/g?y'];
        yield '#s (fragment only)' => ['#s', 'http://a/b/c/d;p?q'];
        yield 'empty' => ['', 'http://a/b/c/d;p?q'];
        yield '.' => ['.', 'http://a/b/c/'];
        yield '..' => ['..', 'http://a/b/'];
        yield '../g' => ['../g', 'http://a/b/g'];
        yield '../..' => ['../..', 'http://a/'];
        yield '../../../g' => ['../../../g', 'http://a/g'];
        yield 'absolute' => ['https://other.example/x', 'https://other.example/x'];
    }

    #[DataProvider('rfc3986Resolution')]
    public function testResolvesReferences(string $reference, string $expected): void
    {
        self::assertSame($expected, (string) Url::parse('http://a/b/c/d;p?q')->resolve($reference));
    }

    public function testOrigin(): void
    {
        $url = Url::parse('http://localhost:8000/a?b');

        self::assertSame('http://localhost:8000', $url->origin());
        self::assertSame('/a?b', $url->pathAndQuery());
        self::assertTrue($url->isSameOrigin(Url::parse('http://LOCALHOST:8000/other')));
        self::assertFalse($url->isSameOrigin(Url::parse('https://localhost:8000/a')));
    }
}

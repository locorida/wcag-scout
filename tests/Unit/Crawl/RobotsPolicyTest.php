<?php

declare(strict_types=1);

namespace WcagScout\Tests\Unit\Crawl;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use WcagScout\Crawl\RobotsPolicy;
use WcagScout\Crawl\Throttle;
use WcagScout\Crawl\UserAgent;
use WcagScout\Model\Url;

final class RobotsPolicyTest extends TestCase
{
    /** @return iterable<string, array{MockResponse, bool}> */
    public static function responses(): iterable
    {
        yield '200 with rules' => [new MockResponse("User-agent: *\nDisallow: /page"), false];
        yield '404: no robots.txt, allow all' => [new MockResponse('', ['http_code' => 404]), true];
        yield '403: still "unavailable", allow all' => [new MockResponse('', ['http_code' => 403]), true];
        yield '503: unreachable, disallow all' => [new MockResponse('', ['http_code' => 503]), false];
        yield 'network error: disallow all' => [new MockResponse([new TransportException('timeout')]), false];
    }

    #[DataProvider('responses')]
    public function testFollowsRfc9309StatusHandling(MockResponse $response, bool $expected): void
    {
        $policy = new RobotsPolicy(new MockHttpClient($response), new UserAgent(), new Throttle(0.0, new MockClock()));

        self::assertSame($expected, $policy->isAllowed(Url::parse('https://example.com/page')));
    }

    public function testFetchesOncePerOriginWithOwnUserAgent(): void
    {
        $requests = [];
        $http = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $requests[] = [$url, (string) json_encode($options['normalized_headers'] ?? null, JSON_UNESCAPED_SLASHES)];

            return new MockResponse("User-agent: *\nDisallow: /x\nCrawl-delay: 3");
        });
        $policy = new RobotsPolicy($http, new UserAgent('https://contact.example'), new Throttle(0.0, new MockClock()));

        self::assertFalse($policy->isAllowed(Url::parse('https://example.com/x')));
        self::assertTrue($policy->isAllowed(Url::parse('https://example.com/y')));
        self::assertSame(3.0, $policy->crawlDelay(Url::parse('https://example.com/')));
        $policy->isAllowed(Url::parse('https://other.example/x'));

        self::assertCount(2, $requests);
        self::assertSame('https://example.com/robots.txt', $requests[0][0]);
        self::assertSame('https://other.example/robots.txt', $requests[1][0]);
        self::assertStringContainsString('wcag-scout/', $requests[0][1]);
        self::assertStringContainsString('+https://contact.example', $requests[0][1]);
    }
}

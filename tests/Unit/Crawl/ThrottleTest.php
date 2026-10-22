<?php

declare(strict_types=1);

namespace WcagScout\Tests\Unit\Crawl;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use WcagScout\Crawl\Throttle;
use WcagScout\Model\Url;

final class ThrottleTest extends TestCase
{
    public function testWaitsOnlyBetweenRequestsToTheSameOrigin(): void
    {
        $clock = new MockClock('2026-01-01 00:00:00');
        $throttle = new Throttle(1.0, $clock);
        $a = Url::parse('https://a.example/1');
        $b = Url::parse('https://b.example/1');

        $throttle->wait($a);
        $throttle->wait($b);
        self::assertSame('00:00:00.000000', $clock->now()->format('H:i:s.u'), 'first request per origin is immediate');

        $throttle->wait($a);
        self::assertSame('00:00:01.000000', $clock->now()->format('H:i:s.u'));

        $clock->sleep(0.4);
        $throttle->wait($a);
        self::assertSame('00:00:02.000000', $clock->now()->format('H:i:s.u'), 'only the remaining 0.6 s are waited');

        $clock->sleep(5);
        $throttle->wait($a);
        self::assertSame('00:00:07.000000', $clock->now()->format('H:i:s.u'), 'no wait when enough time has passed');
    }

    public function testRobotsCrawlDelayCanOnlyLengthenThePause(): void
    {
        $throttle = new Throttle(2.0, new MockClock());
        $url = Url::parse('https://a.example/');

        $throttle->requireDelay($url, 0.5);
        self::assertSame(2.0, $throttle->delayFor($url));

        $throttle->requireDelay($url, 10.0);
        self::assertSame(10.0, $throttle->delayFor($url));
        self::assertSame(2.0, $throttle->delayFor(Url::parse('https://b.example/')));
    }
}

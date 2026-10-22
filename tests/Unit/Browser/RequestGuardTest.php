<?php

declare(strict_types=1);

namespace WcagScout\Tests\Unit\Browser;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WcagScout\Browser\GuardDecision;
use WcagScout\Browser\RequestGuard;

final class RequestGuardTest extends TestCase
{
    /** @return iterable<string, array{string, string, bool, bool, GuardDecision}> */
    public static function requests(): iterable
    {
        yield 'initial page load' => ['GET', 'Document', true, false, GuardDecision::Allow];
        yield 'stylesheet' => ['GET', 'Stylesheet', true, true, GuardDecision::Allow];
        yield 'image after load' => ['GET', 'Image', true, true, GuardDecision::Allow];
        yield 'HEAD request' => ['HEAD', 'Fetch', true, true, GuardDecision::Allow];
        yield 'CORS preflight' => ['OPTIONS', 'Preflight', true, true, GuardDecision::Allow];
        yield 'fetch POST' => ['POST', 'Fetch', true, true, GuardDecision::Block];
        yield 'XHR PUT' => ['PUT', 'XHR', true, true, GuardDecision::Block];
        yield 'beacon' => ['POST', 'Ping', true, true, GuardDecision::Block];
        yield 'DELETE before load' => ['delete', 'Fetch', true, false, GuardDecision::Block];
        yield 'POST form submission after load' => ['POST', 'Document', true, true, GuardDecision::AbortNavigation];
        yield 'GET navigation after load' => ['GET', 'Document', true, true, GuardDecision::AbortNavigation];
        yield 'iframe document after load' => ['GET', 'Document', false, true, GuardDecision::Allow];
        yield 'iframe POST' => ['POST', 'Document', false, true, GuardDecision::Block];
    }

    #[DataProvider('requests')]
    public function testDecides(string $method, string $type, bool $mainFrame, bool $frozen, GuardDecision $expected): void
    {
        self::assertSame($expected, new RequestGuard()->decide($method, $type, $mainFrame, $frozen));
    }

    public function testAbortedNavigationKeepsThePage(): void
    {
        self::assertSame('Aborted', GuardDecision::AbortNavigation->cdpErrorReason());
        self::assertSame('BlockedByClient', GuardDecision::Block->cdpErrorReason());
        self::assertNull(GuardDecision::Allow->cdpErrorReason());
    }
}

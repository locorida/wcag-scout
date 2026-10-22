<?php

declare(strict_types=1);

namespace WcagScout\Tests\Unit\Cli;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\ApplicationTester;
use WcagScout\Cli\Application;

final class ApplicationTest extends TestCase
{
    public function testListsCommandsWithNameAndVersion(): void
    {
        $application = new Application();
        $application->setAutoExit(false);
        $tester = new ApplicationTester($application);

        $exitCode = $tester->run(['command' => 'list']);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('wcag-scout ' . Application::VERSION, $tester->getDisplay());
    }
}

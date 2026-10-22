<?php

declare(strict_types=1);

namespace WcagScout\Tests\Unit\Cli;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Tester\CommandTester;
use WcagScout\Cli\AuditCommand;
use WcagScout\Cli\AuditConfig;
use WcagScout\Cli\ExitCode;
use WcagScout\Model\Severity;
use WcagScout\Standards\En301549Version;
use WcagScout\Standards\Level;

final class AuditConfigTest extends TestCase
{
    public function testDefaults(): void
    {
        $config = self::config(['urls' => ['https://example.com/', 'https://example.com/a']]);

        self::assertCount(2, $config->urls);
        self::assertSame(['html', 'json'], $config->formats);
        self::assertSame(Level::AA, $config->audit->level);
        self::assertTrue($config->audit->bestPractices);
        self::assertSame('en', $config->audit->language);
        self::assertSame(50, $config->crawl->maxPages);
        self::assertSame(1.0, $config->crawl->delaySeconds);
        self::assertFalse($config->crawl->followLinks);
        self::assertSame(En301549Version::V3_2_1, $config->en301549);
        self::assertSame(Severity::Minor, $config->failOn);
        self::assertSame(['https://example.com'], $config->scopeOrigins());
        $userAgent = $config->record()['userAgent'];
        self::assertIsString($userAgent);
        self::assertStringStartsWith('wcag-scout/', $userAgent);
    }

    public function testAllOptions(): void
    {
        $config = self::config([
            '--sitemap' => 'https://example.com/sitemap.xml',
            '--format' => 'PDF, junit,pdf',
            '--lang' => 'DE',
            '--level' => 'a',
            '--no-best-practices' => true,
            '--max-pages' => '5',
            '--delay' => '2.5',
            '--follow-links' => true,
            '--max-depth' => '1',
            '--en301549' => 'V4.1.1',
            '--fail-on' => 'never',
            '--screenshots' => '0',
        ]);

        self::assertNotNull($config->sitemap);
        self::assertSame(['pdf', 'junit'], $config->formats);
        self::assertSame('de', $config->audit->language);
        self::assertSame(Level::A, $config->audit->level);
        self::assertFalse($config->audit->bestPractices);
        self::assertSame(5, $config->crawl->maxPages);
        self::assertSame(2.5, $config->crawl->delaySeconds);
        self::assertTrue($config->crawl->followLinks);
        self::assertSame(En301549Version::V4_1_1, $config->en301549);
        self::assertNull($config->failOn);
        self::assertSame(0, $config->screenshotsPerRule);
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function invalidInput(): iterable
    {
        yield 'no source' => [[], 'exactly one of'];
        yield 'two sources' => [['urls' => ['https://example.com/'], '--sitemap' => 'https://example.com/s.xml'], 'exactly one of'];
        yield 'relative URL' => [['urls' => ['example.com']], 'not an absolute URL'];
        yield 'bad level' => [['urls' => ['https://example.com/'], '--level' => 'AAAA'], '--level'];
        yield 'bad language' => [['urls' => ['https://example.com/'], '--lang' => 'fr'], 'Unsupported language'];
        yield 'zero pages' => [['urls' => ['https://example.com/'], '--max-pages' => '0'], '--max-pages'];
        yield 'negative delay' => [['urls' => ['https://example.com/'], '--delay' => '-1'], '--delay'];
        yield 'bad severity' => [['urls' => ['https://example.com/'], '--fail-on' => 'sometimes'], 'Unknown severity'];
        yield 'bad EN version' => [['urls' => ['https://example.com/'], '--en301549' => '2.1.2'], 'EN 301 549 version'];
        yield 'short timeout' => [['urls' => ['https://example.com/'], '--timeout' => '1'], '--timeout'];
    }

    /** @param array<string, mixed> $input */
    #[DataProvider('invalidInput')]
    public function testRejectsInvalidInput(array $input, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        self::config($input);
    }

    public function testCommandReportsUsageErrorsWithExitCode2(): void
    {
        $tester = new CommandTester(new AuditCommand());

        self::assertSame(ExitCode::USAGE, $tester->execute(['--level' => 'X', 'urls' => ['https://example.com/']]));
    }

    /** @param array<string, mixed> $parameters */
    private static function config(array $parameters): AuditConfig
    {
        $command = new AuditCommand();
        $input = new ArrayInput($parameters, $command->getDefinition());

        return AuditConfig::fromInput($input);
    }
}

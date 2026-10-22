<?php

declare(strict_types=1);

namespace WcagScout\Tests\Unit\Cli;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use WcagScout\Baseline\Baseline;
use WcagScout\Cli\BaselineCommand;
use WcagScout\Cli\CompareCommand;
use WcagScout\Cli\ExitCode;
use WcagScout\Cli\RenderCommand;
use WcagScout\Model\PageResult;
use WcagScout\Report\Json\RunSerializer;
use WcagScout\Tests\Support\RunFactory;
use WcagScout\Tests\Support\TempDirectory;

/**
 * Commands that work on JSON reports and need no browser.
 */
final class ReportCommandsTest extends TestCase
{
    private string $directory;
    private string $report;

    protected function setUp(): void
    {
        $this->directory = TempDirectory::path();
        mkdir($this->directory);
        $this->report = $this->directory . '/report.json';
        file_put_contents($this->report, new RunSerializer()->serialize(RunFactory::sample()));
    }

    protected function tearDown(): void
    {
        TempDirectory::remove($this->directory);
    }

    public function testBaselineCommandWritesAllFindings(): void
    {
        $tester = new CommandTester(new BaselineCommand());

        self::assertSame(ExitCode::OK, $tester->execute(['report' => $this->report, '--output' => $this->directory . '/baseline.json']));
        self::assertCount(6, Baseline::fromFile($this->directory . '/baseline.json')->entries);
    }

    public function testCompareCommandExitCodeReflectsNewFindings(): void
    {
        $tester = new CommandTester(new CompareCommand());

        self::assertSame(ExitCode::OK, $tester->execute(['before' => $this->report, 'after' => $this->report, '--output' => $this->directory . '/cmp']));
        self::assertFileExists($this->directory . '/cmp/comparison.html');
        self::assertFileExists($this->directory . '/cmp/comparison.json');

        $run = RunFactory::sample();
        $withoutFindings = $run->withPages(array_map(static fn(PageResult $p): PageResult => $p->withFindings([]), $run->pages));
        $empty = $this->directory . '/empty.json';
        file_put_contents($empty, new RunSerializer()->serialize($withoutFindings));
        self::assertSame(ExitCode::FINDINGS, $tester->execute(['before' => $empty, 'after' => $this->report, '--output' => $this->directory . '/cmp2']));
    }

    public function testCommandsRejectBrokenInput(): void
    {
        file_put_contents($this->directory . '/broken.json', '{"schemaVersion": 1}');

        self::assertSame(ExitCode::USAGE, new CommandTester(new BaselineCommand())->execute(['report' => $this->directory . '/broken.json']));
        self::assertSame(ExitCode::USAGE, new CommandTester(new CompareCommand())->execute(['before' => $this->report, 'after' => $this->directory . '/missing.json']));
        self::assertSame(ExitCode::USAGE, new CommandTester(new CompareCommand())->execute(['before' => $this->report, 'after' => $this->report, '--lang' => 'fr']));
    }

    public function testRenderCommandRebuildsHtmlInAnotherLanguage(): void
    {
        $tester = new CommandTester(new RenderCommand());

        self::assertSame(ExitCode::OK, $tester->execute(['report' => $this->report, '--format' => 'html,junit', '--lang' => 'de']));
        self::assertStringContainsString('<html lang="de">', (string) file_get_contents($this->directory . '/report.html'));
        self::assertFileExists($this->directory . '/junit.xml');

        self::assertSame(ExitCode::USAGE, $tester->execute(['report' => $this->report, '--format' => 'json']));
    }
}

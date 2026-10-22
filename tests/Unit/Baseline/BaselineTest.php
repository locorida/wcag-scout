<?php

declare(strict_types=1);

namespace WcagScout\Tests\Unit\Baseline;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WcagScout\Baseline\Baseline;
use WcagScout\Model\Finding;
use WcagScout\Tests\Support\RunFactory;
use WcagScout\Tests\Support\TempDirectory;

final class BaselineTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = TempDirectory::path();
    }

    protected function tearDown(): void
    {
        TempDirectory::remove($this->directory);
    }

    public function testContainsEveryFindingInStableOrder(): void
    {
        $run = RunFactory::sample();
        $baseline = Baseline::fromRun($run);

        $expected = array_map(static fn(Finding $f): string => (string) $f->fingerprint, $run->findings());
        $actual = array_keys($baseline->fingerprints());
        sort($expected);
        sort($actual);
        self::assertSame($expected, $actual);
        $urls = array_column($baseline->entries, 'url');
        $sorted = $urls;
        sort($sorted);
        self::assertSame($sorted, $urls, 'sorted by page, so the file diffs well');
    }

    public function testSavesAndLoads(): void
    {
        $baseline = Baseline::fromRun(RunFactory::sample(), new DateTimeImmutable('2026-05-01T12:00:00+00:00'));
        $file = $baseline->save($this->directory . '/baseline.json');

        $loaded = Baseline::fromFile($file);

        self::assertSame($baseline->entries, $loaded->entries);
        self::assertSame($baseline->fingerprints(), $loaded->fingerprints());
        self::assertSame('2026-05-01T12:00:00+00:00', $loaded->createdAt->format(DATE_ATOM));
        self::assertStringContainsString('"selector": "main > img:nth-child(1)"', (string) file_get_contents($file), 'reviewable in a pull request');
    }

    public function testRejectsBrokenFiles(): void
    {
        mkdir($this->directory);
        file_put_contents($this->directory . '/broken.json', '{"schemaVersion": 1, "createdAt": "2026-01-01", "findings": [{"fingerprint": 1}]}');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('baseline.findings[0].fingerprint must be a string');
        Baseline::fromFile($this->directory . '/broken.json');
    }

    public function testRejectsMissingFile(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Baseline::fromFile($this->directory . '/missing.json');
    }
}

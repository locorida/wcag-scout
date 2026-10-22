<?php

declare(strict_types=1);

namespace WcagScout\Tests\Unit\Input;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WcagScout\Input\UrlListSource;

final class UrlListSourceTest extends TestCase
{
    public function testReadsFileWithCommentsAndBlankLines(): void
    {
        $file = $this->file("# pages to check\nhttps://example.com/\n\n  https://example.com/about  # team\r\n");

        $urls = array_map(strval(...), iterator_to_array(UrlListSource::fromFile($file)->urls(), false));

        self::assertSame(['https://example.com/', 'https://example.com/about'], $urls);
    }

    public function testReportsLineOfInvalidEntry(): void
    {
        $file = $this->file("https://example.com/\nexample.com/no-scheme\n");

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('line 2');
        UrlListSource::fromFile($file);
    }

    public function testFailsOnMissingFile(): void
    {
        $this->expectException(RuntimeException::class);
        UrlListSource::fromFile('/does/not/exist.txt');
    }

    private function file(string $content): string
    {
        $file = tempnam(sys_get_temp_dir(), 'urls');
        self::assertIsString($file);
        file_put_contents($file, $content);
        register_shutdown_function(static fn() => @unlink($file));

        return $file;
    }
}

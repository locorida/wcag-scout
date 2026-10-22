<?php

declare(strict_types=1);

namespace WcagScout\Report;

use RuntimeException;

final class FileWriter
{
    /** Writes atomically (temp file + rename), so readers never see half a report. */
    public static function write(string $path, string $content): string
    {
        $directory = \dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0o777, true) && !is_dir($directory)) {
            throw new RuntimeException(\sprintf('Cannot create directory "%s".', $directory));
        }
        $temp = $path . '.tmp';
        if (file_put_contents($temp, $content) === false || !rename($temp, $path)) {
            throw new RuntimeException(\sprintf('Cannot write "%s".', $path));
        }

        return $path;
    }
}

<?php

declare(strict_types=1);

namespace WcagScout\Tests\Unit\Browser;

use PHPUnit\Framework\TestCase;
use WcagScout\Browser\ScreenshotClip;

final class ScreenshotClipTest extends TestCase
{
    public function testSmallElementIsCentredInMinimumClip(): void
    {
        $clip = ScreenshotClip::around(600, 1000, 60, 20, 1280, 3000);

        self::assertSame([390.0, 875.0, 480.0, 270.0], [$clip->x, $clip->y, $clip->width, $clip->height]);
    }

    public function testClipStaysInsideThePage(): void
    {
        $topLeft = ScreenshotClip::around(5, 5, 60, 20, 1280, 3000);
        self::assertSame([0.0, 0.0], [$topLeft->x, $topLeft->y]);

        $bottomRight = ScreenshotClip::around(1250, 2990, 20, 8, 1280, 3000);
        self::assertSame([800.0, 2730.0], [$bottomRight->x, $bottomRight->y]);
    }

    public function testLargeElementIsCappedAndAnchoredAtItsTopLeft(): void
    {
        $clip = ScreenshotClip::around(100, 400, 1180, 2000, 1280, 5000);

        self::assertSame([68.0, 368.0, 1200.0, 700.0], [$clip->x, $clip->y, $clip->width, $clip->height]);
    }

    public function testPageSmallerThanMinimum(): void
    {
        $clip = ScreenshotClip::around(10, 10, 50, 50, 300, 200);

        self::assertSame([0.0, 0.0, 300.0, 200.0], [$clip->x, $clip->y, $clip->width, $clip->height]);
    }
}

<?php

declare(strict_types=1);

namespace WcagScout\Browser;

/**
 * The area around an element that goes into its screenshot: the element plus enough of its
 * surroundings to recognise where on the page it is, clamped to the page.
 */
final readonly class ScreenshotClip
{
    public const int MIN_WIDTH = 480;
    public const int MIN_HEIGHT = 270;
    public const int MAX_WIDTH = 1200;
    public const int MAX_HEIGHT = 700;
    private const int PADDING = 32;

    private function __construct(
        public float $x,
        public float $y,
        public float $width,
        public float $height,
    ) {}

    /**
     * @param float $x element position in document coordinates
     * @param float $pageWidth full scrollable size of the document
     */
    public static function around(float $x, float $y, float $width, float $height, float $pageWidth, float $pageHeight): self
    {
        $clipWidth = min(self::MAX_WIDTH, max(self::MIN_WIDTH, $width + 2 * self::PADDING), $pageWidth);
        $clipHeight = min(self::MAX_HEIGHT, max(self::MIN_HEIGHT, $height + 2 * self::PADDING), $pageHeight);

        // Centre on the element; for elements larger than the clip, keep their top-left corner.
        $left = $width + 2 * self::PADDING > $clipWidth ? $x - self::PADDING : $x + $width / 2 - $clipWidth / 2;
        $top = $height + 2 * self::PADDING > $clipHeight ? $y - self::PADDING : $y + $height / 2 - $clipHeight / 2;

        return new self(
            max(0.0, min($left, $pageWidth - $clipWidth)),
            max(0.0, min($top, $pageHeight - $clipHeight)),
            $clipWidth,
            $clipHeight,
        );
    }
}

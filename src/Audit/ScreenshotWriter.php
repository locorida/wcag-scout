<?php

declare(strict_types=1);

namespace WcagScout\Audit;

use RuntimeException;
use WcagScout\Browser\BrowserPage;
use WcagScout\Model\Finding;

/**
 * Takes element screenshots for findings and stores them next to the report.
 *
 * Only the first few findings per rule and page get a screenshot: a page with 80 images
 * without alt text needs a handful of examples, not 80 pictures.
 */
final readonly class ScreenshotWriter
{
    public const string DIRECTORY = 'screenshots';

    public function __construct(
        private string $reportDirectory,
        private int $perRuleAndPage = 3,
    ) {}

    /**
     * @param list<Finding> $findings all findings of one page
     * @return list<Finding> the same findings, some with a screenshot path
     */
    public function capture(BrowserPage $page, array $findings): array
    {
        $directory = $this->reportDirectory . '/' . self::DIRECTORY;
        if (!is_dir($directory) && !mkdir($directory, 0o777, true) && !is_dir($directory)) {
            throw new RuntimeException(\sprintf('Cannot create directory "%s".', $directory));
        }

        $taken = [];
        $result = [];
        foreach ($findings as $finding) {
            $count = $taken[$finding->ruleId] ?? 0;
            if ($count >= $this->perRuleAndPage) {
                $result[] = $finding;
                continue;
            }

            $file = substr((string) $finding->fingerprint, 0, 20) . '.png';
            $relative = self::DIRECTORY . '/' . $file;
            if (is_file($directory . '/' . $file) || $page->screenshotElement($finding->selector, $directory . '/' . $file)) {
                $finding = $finding->withScreenshot($relative);
                $taken[$finding->ruleId] = $count + 1;
            }
            $result[] = $finding;
        }

        return $result;
    }
}

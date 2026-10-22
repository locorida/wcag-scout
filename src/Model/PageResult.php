<?php

declare(strict_types=1);

namespace WcagScout\Model;

use LogicException;

final readonly class PageResult
{
    /**
     * @param list<Finding> $findings
     * @param string|null $reason why the page was skipped or failed
     */
    private function __construct(
        public Url $url,
        public PageStatus $status,
        public ?string $title,
        public ?int $httpStatus,
        public array $findings,
        public ?string $reason,
        public int $durationMs,
    ) {}

    /** @param list<Finding> $findings */
    public static function audited(Url $url, ?string $title, ?int $httpStatus, array $findings, int $durationMs): self
    {
        foreach ($findings as $finding) {
            if (!$finding->pageUrl->equals($url)) {
                throw new LogicException(\sprintf('Finding for %s attached to page %s.', $finding->pageUrl, $url));
            }
        }

        return new self($url, PageStatus::Audited, $title, $httpStatus, $findings, null, $durationMs);
    }

    public static function skipped(Url $url, string $reason): self
    {
        return new self($url, PageStatus::Skipped, null, null, [], $reason, 0);
    }

    public static function failed(Url $url, string $reason, ?int $httpStatus = null, int $durationMs = 0): self
    {
        return new self($url, PageStatus::Failed, null, $httpStatus, [], $reason, $durationMs);
    }

    /** @param list<Finding> $findings */
    public function withFindings(array $findings): self
    {
        return new self($this->url, $this->status, $this->title, $this->httpStatus, $findings, $this->reason, $this->durationMs);
    }
}

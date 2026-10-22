<?php

declare(strict_types=1);

namespace WcagScout\Browser;

final readonly class NavigationInfo
{
    public function __construct(
        /** URL of the document after redirects. */
        public string $finalUrl,
        public ?int $httpStatus,
        public ?string $mimeType,
        public ?string $title,
        /** The load event did not fire in time; the page was audited in its current state. */
        public bool $loadTimedOut = false,
    ) {}

    public function isHtml(): bool
    {
        return $this->mimeType === null || \in_array($this->mimeType, ['text/html', 'application/xhtml+xml'], true);
    }
}

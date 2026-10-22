<?php

declare(strict_types=1);

namespace WcagScout\Tests\Support;

use WcagScout\Browser\BrowserPage;
use WcagScout\Engine\AuditEngine;
use WcagScout\Model\Finding;
use WcagScout\Model\Severity;
use WcagScout\Model\Url;

final class FakeEngine implements AuditEngine
{
    public int $runs = 0;

    public function __construct(private readonly string $rule, private readonly int $findings) {}

    public function name(): string
    {
        return 'fake';
    }

    public function version(): string
    {
        return '1.0';
    }

    public function run(BrowserPage $page, Url $url): array
    {
        ++$this->runs;

        return array_map(
            fn(int $i): Finding => new Finding($this->rule, 'fake', Severity::Serious, [], $url, '#el' . $i, '<p>', '', ''),
            range(1, $this->findings),
        );
    }

    public function rules(): array
    {
        return [];
    }
}

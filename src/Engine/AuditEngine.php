<?php

declare(strict_types=1);

namespace WcagScout\Engine;

use WcagScout\Browser\BrowserException;
use WcagScout\Browser\BrowserPage;
use WcagScout\Model\Finding;
use WcagScout\Model\RuleInfo;
use WcagScout\Model\Url;

/**
 * A set of accessibility checks that runs inside a loaded page. axe-core is the first one;
 * custom checks (e.g. keyboard focus order) can be added as further engines.
 */
interface AuditEngine
{
    public function name(): string;

    public function version(): string;

    /**
     * @return list<Finding>
     * @throws BrowserException
     */
    public function run(BrowserPage $page, Url $url): array;

    /** @return array<string, RuleInfo> metadata of every rule that has reported findings so far */
    public function rules(): array;
}

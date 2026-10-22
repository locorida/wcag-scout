<?php

declare(strict_types=1);

namespace WcagScout\Engine\Axe;

use RuntimeException;
use WcagScout\Browser\BrowserException;
use WcagScout\Browser\BrowserPage;
use WcagScout\Engine\AuditEngine;
use WcagScout\Engine\AuditOptions;
use WcagScout\Model\RuleInfo;
use WcagScout\Model\Url;
use WcagScout\Standards\Level;

/**
 * Runs the bundled axe-core inside the page.
 */
final class AxeEngine implements AuditEngine
{
    public const string NAME = 'axe-core';
    public const string VERSION = '4.14.0';

    private const string RESOURCES = __DIR__ . '/../../../resources/axe';

    private ?string $script = null;

    /** @var array<string, RuleInfo> */
    private array $rules = [];

    public function __construct(
        private readonly AuditOptions $options,
        private readonly AxeResultMapper $mapper = new AxeResultMapper(),
    ) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function version(): string
    {
        return self::VERSION;
    }

    public function run(BrowserPage $page, Url $url): array
    {
        $page->evaluate($this->script());

        $runOptions = json_encode([
            'runOnly' => ['type' => 'tag', 'values' => $this->tags()],
            'resultTypes' => ['violations'],
            // Frames would need axe injected into each of them; they are out of scope for now.
            'iframes' => false,
        ], JSON_THROW_ON_ERROR);

        $result = $page->evaluate(
            "axe.run(document, {$runOptions}).then(r => ({version: r.testEngine.version, violations: r.violations}))",
            60_000,
        );
        if (!\is_array($result) || !isset($result['violations']) || !\is_array($result['violations'])) {
            throw new BrowserException('axe-core returned no result.');
        }
        if (($result['version'] ?? null) !== self::VERSION) {
            throw new BrowserException('Unexpected axe-core version in page: ' . json_encode($result['version'] ?? null));
        }

        $mapped = $this->mapper->map($result['violations'], $url);
        $this->rules += $mapped->rules;

        return $mapped->findings;
    }

    public function rules(): array
    {
        return $this->rules;
    }

    /** @return list<string> axe-core tags to run, derived from the target level */
    public function tags(): array
    {
        $tags = ['wcag2a', 'wcag21a', 'wcag22a'];
        if (Level::AA->isWithin($this->options->level)) {
            array_push($tags, 'wcag2aa', 'wcag21aa', 'wcag22aa');
        }
        if (Level::AAA->isWithin($this->options->level)) {
            array_push($tags, 'wcag2aaa', 'wcag21aaa', 'wcag22aaa');
        }
        if ($this->options->bestPractices) {
            $tags[] = 'best-practice';
        }

        return $tags;
    }

    /** axe-core itself, plus the German locale when needed. */
    private function script(): string
    {
        if ($this->script !== null) {
            return $this->script;
        }

        $script = self::read(self::RESOURCES . '/axe.min.js');
        if ($this->options->language === 'de') {
            $script .= ";\naxe.configure({locale: " . self::read(self::RESOURCES . '/locales/de.json') . '});';
        }

        return $this->script = $script . ";\ntrue";
    }

    private static function read(string $path): string
    {
        $content = is_file($path) ? file_get_contents($path) : false;
        if ($content === false) {
            throw new RuntimeException(\sprintf('Missing axe-core resource "%s".', $path));
        }

        return $content;
    }
}

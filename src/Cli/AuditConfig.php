<?php

declare(strict_types=1);

namespace WcagScout\Cli;

use InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;
use WcagScout\Crawl\CrawlOptions;
use WcagScout\Crawl\UserAgent;
use WcagScout\Engine\AuditOptions;
use WcagScout\Model\Severity;
use WcagScout\Model\Url;
use WcagScout\Standards\En301549Version;
use WcagScout\Standards\Level;

/**
 * Validated options of the audit command.
 */
final readonly class AuditConfig
{
    public const array DEFAULT_FORMATS = ['html', 'json'];

    /**
     * @param list<Url> $urls
     * @param list<string> $formats
     */
    public function __construct(
        public array $urls,
        public ?string $urlsFile,
        public ?Url $sitemap,
        public string $outputDirectory,
        public array $formats,
        public AuditOptions $audit,
        public CrawlOptions $crawl,
        public En301549Version $en301549,
        public ?Severity $failOn,
        public ?string $baseline,
        public string $contactUrl,
        public int $timeoutSeconds,
        public int $screenshotsPerRule,
        public ?string $chromePath,
    ) {}

    /** @throws InvalidArgumentException with a message for the user */
    public static function fromInput(InputInterface $input): self
    {
        $urls = array_map(Url::parse(...), self::stringList($input->getArgument('urls')));
        $urlsFile = self::optionalString($input->getOption('urls-file'));
        $sitemap = self::optionalString($input->getOption('sitemap'));

        $sources = \count(array_filter([$urls !== [], $urlsFile !== null, $sitemap !== null]));
        if ($sources !== 1) {
            throw new InvalidArgumentException('Give exactly one of: URLs as arguments, --urls-file or --sitemap.');
        }

        $formats = array_values(array_unique(array_filter(array_map(
            static fn(string $f): string => strtolower(trim($f)),
            explode(',', self::string($input->getOption('format'))),
        ), static fn(string $f): bool => $f !== '')));
        if ($formats === []) {
            throw new InvalidArgumentException('--format needs at least one format.');
        }

        $level = Level::tryFrom(strtoupper(self::string($input->getOption('level'))));
        if ($level === null) {
            throw new InvalidArgumentException('--level must be A, AA or AAA.');
        }

        $failOnValue = strtolower(self::string($input->getOption('fail-on')));

        return new self(
            $urls,
            $urlsFile,
            $sitemap === null ? null : Url::parse($sitemap),
            self::string($input->getOption('output')),
            $formats,
            new AuditOptions($level, !(bool) $input->getOption('no-best-practices'), strtolower(self::string($input->getOption('lang')))),
            new CrawlOptions(
                self::int($input->getOption('max-pages'), '--max-pages'),
                self::float($input->getOption('delay'), '--delay'),
                (bool) $input->getOption('follow-links'),
                self::int($input->getOption('max-depth'), '--max-depth'),
            ),
            En301549Version::fromOption(self::string($input->getOption('en301549'))),
            $failOnValue === 'never' ? null : Severity::fromOption($failOnValue),
            self::optionalString($input->getOption('baseline')),
            Url::parse(self::string($input->getOption('contact-url')))->__toString(),
            self::int($input->getOption('timeout'), '--timeout', min: 5),
            self::int($input->getOption('screenshots'), '--screenshots', min: 0),
            self::optionalString($input->getOption('chrome-path')),
        );
    }

    /**
     * Sites the crawl may visit: those of the given URLs, or of the sitemap. For a URL file,
     * the first URL defines the site.
     *
     * @return list<string>
     */
    public function scopeOrigins(): array
    {
        $urls = $this->sitemap !== null ? [$this->sitemap] : $this->urls;

        return array_values(array_unique(array_map(static fn(Url $u): string => $u->origin(), $urls)));
    }

    /** @return array<string, scalar|list<string>|null> what goes into the report as a record */
    public function record(): array
    {
        return [
            'target' => $this->sitemap !== null ? (string) $this->sitemap : ($this->urlsFile ?? implode(' ', array_map(strval(...), $this->urls))),
            'language' => $this->audit->language,
            'level' => $this->audit->level->value,
            'bestPractices' => $this->audit->bestPractices,
            'en301549' => $this->en301549->value,
            'maxPages' => $this->crawl->maxPages,
            'delaySeconds' => $this->crawl->delaySeconds,
            'followLinks' => $this->crawl->followLinks,
            'maxDepth' => $this->crawl->maxDepth,
            'userAgent' => new UserAgent($this->contactUrl)->forHttp(),
        ];
    }

    private static function string(mixed $value): string
    {
        return \is_string($value) ? $value : '';
    }

    private static function optionalString(mixed $value): ?string
    {
        return \is_string($value) && $value !== '' ? $value : null;
    }

    /** @return list<string> */
    private static function stringList(mixed $value): array
    {
        return \is_array($value) ? array_values(array_filter($value, \is_string(...))) : [];
    }

    private static function int(mixed $value, string $name, int $min = 1): int
    {
        if (!\is_string($value) || preg_match('/^\d+$/', $value) !== 1 || (int) $value < $min) {
            throw new InvalidArgumentException(\sprintf('%s must be a whole number of at least %d.', $name, $min));
        }

        return (int) $value;
    }

    private static function float(mixed $value, string $name): float
    {
        if (!\is_string($value) || !is_numeric($value) || (float) $value < 0) {
            throw new InvalidArgumentException(\sprintf('%s must be a number of at least 0.', $name));
        }

        return (float) $value;
    }
}

<?php

declare(strict_types=1);

namespace WcagScout\Compare;

use DateTimeInterface;
use Stringable;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use WcagScout\Knowledge\KnowledgeBase;
use WcagScout\Model\Finding;
use WcagScout\Report\FileWriter;
use WcagScout\Report\TranslatorFactory;

/**
 * Writes a comparison as HTML (for people) and JSON (for tools).
 */
final readonly class ComparisonRenderer
{
    public const string HTML_FILE = 'comparison.html';
    public const string JSON_FILE = 'comparison.json';
    public const array CATEGORIES = ['added', 'fixed', 'unchanged', 'notRechecked'];

    private const string TEMPLATES = __DIR__ . '/../../resources/templates';

    public function __construct(
        private KnowledgeBase $knowledge = new KnowledgeBase(),
    ) {}

    /** @return list<string> written files */
    public function write(Comparison $comparison, string $language, string $directory): array
    {
        return [
            FileWriter::write($directory . '/' . self::JSON_FILE, $this->json($comparison)),
            FileWriter::write($directory . '/' . self::HTML_FILE, $this->html($comparison, $language)),
        ];
    }

    public function json(Comparison $comparison): string
    {
        $data = [
            'schemaVersion' => 1,
            'before' => ['startedAt' => $comparison->before->startedAt->format(DateTimeInterface::ATOM), 'findings' => \count($comparison->before->findings())],
            'after' => ['startedAt' => $comparison->after->startedAt->format(DateTimeInterface::ATOM), 'findings' => \count($comparison->after->findings())],
            'summary' => array_map(\count(...), self::categories($comparison)),
        ];
        foreach (self::categories($comparison) as $category => $findings) {
            $data[$category] = array_map(static fn(Finding $f): array => [
                'fingerprint' => (string) $f->fingerprint,
                'ruleId' => $f->ruleId,
                'severity' => $f->severity->value,
                'url' => (string) $f->pageUrl,
                'selector' => $f->selector,
            ], $findings);
        }

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
    }

    public function html(Comparison $comparison, string $language): string
    {
        $translator = TranslatorFactory::create($language);
        $twig = new Environment(new FilesystemLoader(self::TEMPLATES), ['autoescape' => 'html', 'strict_variables' => true]);
        $twig->addFilter(new TwigFilter(
            't',
            /** @param array<string, mixed> $parameters */
            static fn(string $key, array $parameters = []): string => $translator->trans($key, array_map(
                static fn(mixed $v): string => \is_scalar($v) || $v instanceof Stringable ? (string) $v : '',
                $parameters,
            )),
        ));
        $twig->addFilter(new TwigFilter(
            'rule_title',
            fn(Finding $f): string => $this->knowledge->explain($f->ruleId, $language)->title
                ?? $comparison->after->rule($f->ruleId)->help
                ?? $comparison->before->rule($f->ruleId)->help
                ?? $f->ruleId,
        ));

        return $twig->render('comparison.html.twig', [
            'c' => $comparison,
            'lang' => $language,
            'categories' => self::categories($comparison),
            'site' => $comparison->after->site() ?? $comparison->before->site() ?? '–',
        ]);
    }

    /** @return array<string, list<Finding>> */
    private static function categories(Comparison $comparison): array
    {
        return [
            'added' => $comparison->added,
            'fixed' => $comparison->fixed,
            'unchanged' => $comparison->unchanged,
            'notRechecked' => $comparison->notRechecked,
        ];
    }
}

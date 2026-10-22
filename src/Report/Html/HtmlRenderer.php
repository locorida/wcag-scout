<?php

declare(strict_types=1);

namespace WcagScout\Report\Html;

use Stringable;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;
use WcagScout\Knowledge\KnowledgeBase;
use WcagScout\Model\AuditRun;
use WcagScout\Report\Disclaimer;
use WcagScout\Report\FileWriter;
use WcagScout\Report\Renderer;
use WcagScout\Report\ReportModel;
use WcagScout\Report\ReportOptions;
use WcagScout\Report\TranslatorFactory;

final readonly class HtmlRenderer implements Renderer
{
    public const string FILE = 'report.html';
    private const string TEMPLATES = __DIR__ . '/../../../resources/templates';

    public function __construct(
        private KnowledgeBase $knowledge = new KnowledgeBase(),
    ) {}

    public function format(): string
    {
        return 'html';
    }

    public function render(AuditRun $run, ReportOptions $options, string $directory): string
    {
        return FileWriter::write($directory . '/' . self::FILE, $this->html($run, $options));
    }

    /**
     * @param bool $forPrint expand all details, for the PDF
     */
    public function html(AuditRun $run, ReportOptions $options, bool $forPrint = false): string
    {
        $translator = TranslatorFactory::create($options->language);

        $twig = new Environment(new FilesystemLoader(self::TEMPLATES), [
            'autoescape' => 'html',
            'strict_variables' => true,
        ]);
        $twig->addFilter(new TwigFilter(
            't',
            /** @param array<string, scalar|null> $parameters */
            static fn(string $key, array $parameters = []): string => $translator->trans($key, array_map(
                static fn(mixed $v): string => \is_scalar($v) || $v instanceof Stringable ? (string) $v : '',
                $parameters,
            )),
        ));
        $twig->addFunction(new TwigFunction('disclaimer', Disclaimer::for(...)));

        return $twig->render('report.html.twig', [
            'm' => new ReportModel($run, $options, $this->knowledge),
            'site' => $run->site() ?? '–',
            'pdf' => $forPrint,
        ]);
    }
}

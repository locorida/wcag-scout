<?php

declare(strict_types=1);

namespace WcagScout\Cli;

use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use WcagScout\Browser\ChromeBrowser;
use WcagScout\Crawl\UserAgent;
use WcagScout\Report\Json\RunDeserializer;
use WcagScout\Report\ReportOptions;
use WcagScout\Standards\En301549Version;
use WcagScout\Standards\Level;

#[AsCommand(name: 'render', description: 'Render reports again from a JSON report, e.g. in another language or as PDF.')]
final class RenderCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('report', InputArgument::REQUIRED, 'JSON report of an audit (report.json)')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Comma-separated: html, pdf, junit', 'html')
            ->addOption('lang', 'l', InputOption::VALUE_REQUIRED, 'Report language: en or de (default: language of the audit)')
            ->addOption('en301549', null, InputOption::VALUE_REQUIRED, 'EN 301 549 version: 3.2.1 or 4.1.1 (default: as in the audit)')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Directory (default: the directory of the JSON report, so screenshots resolve)')
            ->setHelp(<<<'HELP'
                Report texts written by wcag-scout follow --lang. The per-element messages from
                axe-core stay in the language of the original audit.
                HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $path = InputValues::argument($input, 'report');

        try {
            $run = new RunDeserializer()->fromFile($path);
            $formats = InputValues::list($input, 'format');
            if (\in_array('json', $formats, true)) {
                throw new InvalidArgumentException('The JSON report is the input of render; choose html, pdf or junit.');
            }

            // Defaults come from the audit as recorded in the JSON report.
            $recorded = static fn(string $key, string $default): string => \is_string($run->options[$key] ?? null) ? $run->options[$key] : $default;
            $options = new ReportOptions(
                strtolower(InputValues::option($input, 'lang') ?? $recorded('language', 'en')),
                Level::tryFrom($recorded('level', 'AA')) ?? Level::AA,
                En301549Version::fromOption(InputValues::option($input, 'en301549') ?? $recorded('en301549', En301549Version::DEFAULT->value)),
            );
        } catch (InvalidArgumentException $e) {
            $io->getErrorStyle()->error($e->getMessage());

            return ExitCode::USAGE;
        }

        $directory = InputValues::option($input, 'output') ?? \dirname($path);
        $browser = new ChromeBrowser(new UserAgent());
        try {
            $files = Reports::writer($browser)->write($run, $options, $directory, $formats);
        } catch (InvalidArgumentException $e) {
            $io->getErrorStyle()->error($e->getMessage());

            return ExitCode::USAGE;
        } catch (RuntimeException $e) {
            $io->getErrorStyle()->error($e->getMessage());

            return ExitCode::ERROR;
        } finally {
            $browser->close();
        }

        $io->listing($files);

        return ExitCode::OK;
    }
}

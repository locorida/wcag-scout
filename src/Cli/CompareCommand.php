<?php

declare(strict_types=1);

namespace WcagScout\Cli;

use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use WcagScout\Compare\ComparisonRenderer;
use WcagScout\Compare\RunComparator;
use WcagScout\Engine\AuditOptions;
use WcagScout\Report\Json\RunDeserializer;

#[AsCommand(name: 'compare', description: 'Compare two JSON reports of the same site: fixed, new and unchanged findings.')]
final class CompareCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('before', InputArgument::REQUIRED, 'JSON report of the earlier run')
            ->addArgument('after', InputArgument::REQUIRED, 'JSON report of the later run')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Directory for comparison.html and comparison.json', 'wcag-scout-comparison')
            ->addOption('lang', 'l', InputOption::VALUE_REQUIRED, 'Language: en or de', 'en')
            ->setHelp('Exit code 1 if the later run has new findings, 0 otherwise.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $language = strtolower(InputValues::option($input, 'lang') ?? 'en');
        try {
            if (!\in_array($language, AuditOptions::LANGUAGES, true)) {
                throw new InvalidArgumentException('--lang must be en or de.');
            }
            $reader = new RunDeserializer();
            $comparison = new RunComparator()->compare(
                $reader->fromFile(InputValues::argument($input, 'before')),
                $reader->fromFile(InputValues::argument($input, 'after')),
            );
        } catch (InvalidArgumentException $e) {
            $io->getErrorStyle()->error($e->getMessage());

            return ExitCode::USAGE;
        }

        $files = new ComparisonRenderer()->write($comparison, $language, (InputValues::option($input, 'output') ?? ''));

        $io->table(['New', 'Fixed', 'Unchanged', 'Not checked again'], [[
            \count($comparison->added),
            \count($comparison->fixed),
            \count($comparison->unchanged),
            \count($comparison->notRechecked),
        ]]);
        $io->listing($files);

        return $comparison->added === [] ? ExitCode::OK : ExitCode::FINDINGS;
    }
}

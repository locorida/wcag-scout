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
use WcagScout\Baseline\Baseline;
use WcagScout\Report\Json\RunDeserializer;

#[AsCommand(name: 'baseline', description: 'Accept the findings of a JSON report as known, for later --baseline runs.')]
final class BaselineCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('report', InputArgument::REQUIRED, 'JSON report of an audit (report.json)')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Baseline file to write', 'wcag-scout-baseline.json')
            ->setHelp(<<<'HELP'
                Typical CI setup:
                  <info>wcag-scout audit https://example.com/ --format json</info>
                  <info>wcag-scout baseline wcag-scout-report/report.json</info>   commit wcag-scout-baseline.json
                  <info>wcag-scout audit https://example.com/ --baseline wcag-scout-baseline.json</info>
                The last command only fails for findings that are not in the baseline.
                HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $run = new RunDeserializer()->fromFile(InputValues::argument($input, 'report'));
        } catch (InvalidArgumentException $e) {
            $io->getErrorStyle()->error($e->getMessage());

            return ExitCode::USAGE;
        }

        $baseline = Baseline::fromRun($run);
        $file = $baseline->save((InputValues::option($input, 'output') ?? ''));
        $io->success(\sprintf('%d known finding(s) written to %s.', \count($baseline->entries), $file));

        return ExitCode::OK;
    }
}

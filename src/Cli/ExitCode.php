<?php

declare(strict_types=1);

namespace WcagScout\Cli;

final class ExitCode
{
    /** No (new) findings at or above the --fail-on threshold. */
    public const int OK = 0;
    /** Findings at or above the threshold (with a baseline: new findings only). */
    public const int FINDINGS = 1;
    /** Invalid options or input files. */
    public const int USAGE = 2;
    /** The audit could not run (browser, network, file system). */
    public const int ERROR = 3;
}

<?php

declare(strict_types=1);

namespace WcagScout\Cli;

use Symfony\Component\Console\Application as ConsoleApplication;

final class Application extends ConsoleApplication
{
    public const string NAME = 'wcag-scout';
    public const string VERSION = '0.1.0-dev';

    public function __construct()
    {
        parent::__construct(self::NAME, self::VERSION);
        $this->addCommands([
            new AuditCommand(),
            new BaselineCommand(),
            new CompareCommand(),
            new RenderCommand(),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace WcagScout\Cli;

use Symfony\Component\Console\Input\InputInterface;

/**
 * Typed access to console input, which Symfony returns as mixed.
 */
final class InputValues
{
    public static function argument(InputInterface $input, string $name): string
    {
        $value = $input->getArgument($name);

        return \is_string($value) ? $value : '';
    }

    public static function option(InputInterface $input, string $name): ?string
    {
        $value = $input->getOption($name);

        return \is_string($value) && $value !== '' ? $value : null;
    }

    /** @return list<string> comma-separated option, lower-cased and trimmed */
    public static function list(InputInterface $input, string $name): array
    {
        $parts = array_map(
            static fn(string $part): string => strtolower(trim($part)),
            explode(',', self::option($input, $name) ?? ''),
        );

        return array_values(array_unique(array_filter($parts, static fn(string $part): bool => $part !== '')));
    }
}

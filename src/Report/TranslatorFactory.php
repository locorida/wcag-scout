<?php

declare(strict_types=1);

namespace WcagScout\Report;

use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Contracts\Translation\TranslatorInterface;
use WcagScout\Engine\AuditOptions;

final class TranslatorFactory
{
    private const string DIRECTORY = __DIR__ . '/../../resources/translations';

    public static function create(string $language): TranslatorInterface
    {
        $translator = new Translator($language);
        $translator->setFallbackLocales(['en']);
        $translator->addLoader('array', new ArrayLoader());
        foreach (AuditOptions::LANGUAGES as $available) {
            /** @var array<string, string> $messages */
            $messages = require self::DIRECTORY . '/messages.' . $available . '.php';
            $translator->addResource('array', $messages, $available);
        }

        return $translator;
    }
}

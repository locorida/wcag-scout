<?php

declare(strict_types=1);

namespace WcagScout\Report;

/**
 * The notice every report carries, in every format. Kept in one place so no renderer can
 * forget or reword it.
 */
final class Disclaimer
{
    private const array TEXT = [
        'en' => [
            'title' => 'Limits of this report',
            'text' => 'Automated tests detect only part of all accessibility problems. This report is not proof of conformance with WCAG, EN 301 549 or the German Accessibility Strengthening Act (BFSG) and does not replace a manual audit.',
            'manualChecksTitle' => 'What has to be checked manually',
            'manualChecks' => [
                'Keyboard operation: every function can be reached and used without a mouse, with no keyboard trap.',
                'Focus order and visible focus: focus moves in a logical order and is always clearly visible.',
                'Quality of text alternatives: alternative texts describe the content or function of an image, not just exist.',
                'Clear language: texts, instructions and error messages are understandable.',
                'Screen reader test: the pages work with a screen reader such as NVDA, JAWS or VoiceOver.',
                'Zoom and reflow: content stays usable at 200 % zoom and at a width of 320 CSS pixels.',
                'Media: videos have captions, and audio description where needed.',
            ],
        ],
        'de' => [
            'title' => 'Grenzen dieses Reports',
            'text' => 'Automatisierte Tests erkennen nur einen Teil der Barrierefreiheitsprobleme. Der Report ist kein Nachweis der Konformität mit WCAG, EN 301 549 oder BFSG und ersetzt keine manuelle Prüfung.',
            'manualChecksTitle' => 'Was manuell geprüft werden muss',
            'manualChecks' => [
                'Tastaturbedienung: Alle Funktionen sind ohne Maus erreichbar und bedienbar, ohne Tastaturfalle.',
                'Fokusreihenfolge und sichtbarer Fokus: Der Fokus wandert in einer logischen Reihenfolge und ist jederzeit deutlich sichtbar.',
                'Qualität von Alternativtexten: Alternativtexte beschreiben Inhalt oder Funktion eines Bildes und sind nicht nur vorhanden.',
                'Verständliche Sprache: Texte, Anleitungen und Fehlermeldungen sind verständlich.',
                'Screenreader-Test: Die Seiten funktionieren mit einem Screenreader wie NVDA, JAWS oder VoiceOver.',
                'Zoom und Umbruch: Inhalte bleiben bei 200 % Vergrößerung und bei 320 CSS-Pixeln Breite nutzbar.',
                'Medien: Videos haben Untertitel und, wo nötig, Audiodeskription.',
            ],
        ],
    ];

    /** @return array{title: string, text: string, manualChecksTitle: string, manualChecks: list<string>} */
    public static function for(string $language): array
    {
        return self::TEXT[$language] ?? self::TEXT['en'];
    }

    /** @return array<string, array{title: string, text: string, manualChecksTitle: string, manualChecks: list<string>}> */
    public static function all(): array
    {
        return self::TEXT;
    }
}

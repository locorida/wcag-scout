<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in([__DIR__ . '/src', __DIR__ . '/tests'])
    ->exclude('fixtures')
    ->append([__DIR__ . '/bin/wcag-scout', __DIR__ . '/bin/check-licenses']);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setFinder($finder)
    ->setRules([
        '@PER-CS' => true,
        '@PHP8x4Migration' => true,
        'declare_strict_types' => true,
        'strict_comparison' => true,
        'strict_param' => true,
        'native_function_invocation' => ['include' => ['@compiler_optimized'], 'scope' => 'namespaced'],
        'no_unused_imports' => true,
        'ordered_imports' => ['imports_order' => ['class', 'function', 'const'], 'sort_algorithm' => 'alpha'],
        'global_namespace_import' => ['import_classes' => true, 'import_functions' => false, 'import_constants' => false],
        'final_class' => true,
        'void_return' => true,
        'phpdoc_align' => ['align' => 'left'],
        'trailing_comma_in_multiline' => ['elements' => ['arguments', 'arrays', 'match', 'parameters']],
    ]);

<?php

declare(strict_types=1);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        '@PSR12:risky' => true,
        '@PHP81Migration' => true,
        '@DoctrineAnnotation' => true,
        'strict_param' => true,
        'declare_strict_types' => true,
        'blank_line_before_statement' => [
            'statements' => ['return', 'try', 'throw', 'if', 'switch', 'for', 'foreach', 'while', 'do'],
        ],
        'no_unused_imports' => true,
        'no_useless_concat_operator' => true,
        'native_function_invocation' => [
            'include' => ['@compiler_optimized'],
            'scope' => 'namespaced',
            'strict' => true,
        ],
    ])
    ->setFinder(
        PhpCsFixer\Finder::create()
            ->in('src')
            ->in('apps')
            ->in('tests')
            ->name('*.php')
    );

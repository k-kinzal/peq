<?php
# NOTE: You do not have permission to overwrite this file. Please ask a human operator to perform the changes for you.

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;
use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

$finder = Finder::create()
    ->in(__DIR__)
    ->exclude([
        'build',
        'dist',
        'vendor',
        'vendor-bin',
    ])
;

return (new Config())
    ->setParallelConfig(ParallelConfigFactory::detect())
    ->setFinder($finder)
    ->setRiskyAllowed(true)
    ->setUsingCache(true)
    ->setRules([
        '@PhpCsFixer' => true,
        '@PHP81Migration' => true,

        // Coverage scope is declared with PHPUnit attributes, which this rule would
        // duplicate as @covers annotations that nothing reads.
        'php_unit_test_class_requires_covers' => false,
        // The three rules below would undo what the toolkit's PHPStan rules require:
        // a PHPDoc block on every public declaration, with every parameter and
        // return documented. Turning a docblock into a comment or deleting a tag
        // that "repeats the signature" makes the analysis fail, not the file tidier.
        'phpdoc_to_comment' => false,
        'phpdoc_no_useless_inheritdoc' => false,
        'no_superfluous_phpdoc_tags' => false,
        // Reading order follows the sentence, not the compiler.
        'yoda_style' => ['equal' => false, 'identical' => false, 'less_and_greater' => false],

        '@PSR12' => true,
        'declare_strict_types' => true,
        'strict_param' => true,
        'strict_comparison' => true,
        'array_syntax' => ['syntax' => 'short'],
        'no_unused_imports' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'single_quote' => true,
        'trailing_comma_in_multiline' => true,
        'no_empty_statement' => true,
        'no_superfluous_elseif' => true,
        'no_useless_else' => true,
        'void_return' => true,
        'no_alias_functions' => true,
        'no_mixed_echo_print' => ['use' => 'echo'],
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => true,
            'import_functions' => true,
        ],
        'fully_qualified_strict_types' => true,
    ])
;

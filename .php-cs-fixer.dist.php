<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;
use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

$finder = Finder::create()->in([
    __DIR__.'/bin',
    __DIR__.'/src',
    __DIR__.'/tests',
]);

return (new Config())
    ->setParallelConfig(ParallelConfigFactory::detect())
    ->setFinder($finder)
    ->setRiskyAllowed(true)
    ->setUsingCache(true)
    ->setRules([
        '@PhpCsFixer' => true,
        '@PHP83Migration' => true,
        'declare_strict_types' => true,
        'strict_comparison' => true,
        'strict_param' => true,

        'php_unit_test_class_requires_covers' => false,
        'phpdoc_to_comment' => false,
        'phpdoc_no_useless_inheritdoc' => false,
        'no_superfluous_phpdoc_tags' => false,
        'yoda_style' => ['equal' => false, 'identical' => false, 'less_and_greater' => false],
    ])
;

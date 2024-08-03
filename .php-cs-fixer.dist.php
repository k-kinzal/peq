<?php
use PhpCsFixer\Config;
use PhpCsFixer\Finder;
use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

$finder = Finder::create()->in([
    __DIR__.'/bin',
    __DIR__.'/src',
    __DIR__.'/tests'
]);

return (new Config())
    ->setFinder($finder)
    ->setRiskyAllowed(true)
    ->setUsingCache(false)
    ->setParallelConfig(ParallelConfigFactory::detect())
;

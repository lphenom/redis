<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in([__DIR__ . '/src', __DIR__ . '/tests', __DIR__ . '/build'])
    ->name('*.php')
    ->notPath('vendor');

return (new Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12'                              => true,
        'declare_strict_types'                => true,
        'array_syntax'                        => ['syntax' => 'short'],
        'no_unused_imports'                   => true,
        'ordered_imports'                     => ['sort_algorithm' => 'alpha'],
        'phpdoc_align'                        => true,
        'phpdoc_order'                        => true,
        'phpdoc_trim'                         => true,
        'trailing_comma_in_multiline'         => true,
        'single_quote'                        => true,
        'no_extra_blank_lines'                => true,
        'blank_line_after_opening_tag'        => false,
        'linebreak_after_opening_tag'         => false,
    ])
    ->setFinder($finder)
    ->setUsingCache(true)
    ->setCacheFile('.php-cs-fixer.cache');



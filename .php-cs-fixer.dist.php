<?php

declare(strict_types=1);

$finder = (new PhpCsFixer\Finder())
    ->in([__DIR__ . '/src', __DIR__ . '/tests']);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PhpCsFixer' => true,
        'declare_strict_types' => true,

        // Preserve the project's "auditable native inventory": keep the
        // `use function` blocks instead of FQN-prefixing natives with `\`.
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => false,
            'import_functions' => true,
        ],

        // Member order per CLAUDE.md: constants -> properties -> constructor
        // -> non-magic methods -> magic methods (magic kept LAST).
        'ordered_class_elements' => [
            'order' => [
                'use_trait',
                'case',
                'constant_public', 'constant_protected', 'constant_private',
                'property_public', 'property_protected', 'property_private',
                'construct',
                'phpunit',
                'method_public', 'method_protected', 'method_private',
                'magic',
                'destruct',
            ],
        ],

        // Protect the inline `@var` idiom used to document deficient native
        // stubs for PHPStan: never demote those docblocks to plain comments,
        // and never collapse `$x = ...; return $x;` into `return ...;` — that
        // would orphan the `/** @var $x */` sitting above the assignment.
        'phpdoc_to_comment' => ['ignored_tags' => ['var']],
        'return_assignment' => false,

        // Natural comparison order ($x === null), not Yoda.
        'yoda_style' => false,

        // One space around the concatenation operator: 'x ' . $y, not 'x '.$y —
        // the preset's 'none' glues operands together and hurts readability.
        'concat_space' => ['spacing' => 'one'],
    ])
    ->setFinder($finder);

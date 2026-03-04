<?php

declare(strict_types=1);

/**
 * KaririCode Devkit — Project Overrides
 *
 * This file customizes the devkit behavior for this project.
 * Only uncomment the keys you need to change — unset keys use
 * auto-detected values from composer.json + KaririCode defaults.
 *
 * Merge semantics:
 *   - cs_fixer_rules → MERGED with KaririCode defaults (your rules win on conflict)
 *   - rector_sets    → REPLACES KaririCode defaults entirely
 *   - All others     → REPLACES the auto-detected value
 *
 * After editing, run: kcode init
 *
 * @see https://github.com/kariricode/devkit
 */

return [
    // ── Project Identity ──────────────────────────────────────
    // 'project_name'  => 'kariricode/my-component',
    // 'namespace'     => 'KaririCode\\MyComponent',

    // ── PHP Version ───────────────────────────────────────────
    // 'php_version'   => '8.4',

    // ── Static Analysis ───────────────────────────────────────
    // 'phpstan_level' => 9,       // 0–9 (default: 9)
    // 'psalm_level'   => 3,       // 1–9 (default: 3)

    // ── Directories ───────────────────────────────────────────
    // 'source_dirs'   => ['src'],
    // 'test_dirs'     => ['tests'],
    // 'exclude_dirs'  => ['src/Contract'],  // excluded from static analysis

    // ── Test Suites ───────────────────────────────────────────
    // 'test_suites' => [
    //     'Unit'        => 'tests/Unit',
    //     'Integration' => 'tests/Integration',
    // ],

    // ── Coverage ──────────────────────────────────────────────
    // 'coverage_exclude' => ['src/Exception'],

    // ── Code Style (MERGED with KaririCode defaults) ──────────
    // 'cs_fixer_rules' => [
    //     'concat_space' => ['spacing' => 'one'],
    //     'yoda_style'   => false,
    // ],

    // ── Rector (REPLACES KaririCode defaults) ─────────────────
    // 'rector_sets' => [
    //     'LevelSetList::UP_TO_PHP_84',
    //     'SetList::CODE_QUALITY',
    //     'SetList::DEAD_CODE',
    //     'SetList::EARLY_RETURN',
    //     'SetList::TYPE_DECLARATION',
    // ],

    // ── Tool Versions (informational) ─────────────────────────
    // 'tools' => [
    //     'phpunit'      => '^11.0',
    //     'phpstan'      => '^2.0',
    //     'php-cs-fixer' => '^3.64',
    //     'rector'       => '^2.0',
    //     'psalm'        => '^6.0',
    // ],
];

<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Contract;

/**
 * Scanner for recursive directory traversal with security constraints.
 *
 * Provides depth-limited, symlink-safe traversal following PSR-4 conventions.
 * Path traversal prevention via realpath() validation.
 *
 * @package KaririCode\ClassDiscovery\Contract
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   3.0.0
 */
interface DirectoryScanner extends Scanner
{
    /**
     * Set maximum traversal depth (default: 10).
     *
     * @param int $depth Maximum directory nesting level
     *
     * @return self For method chaining
     */
    public function setMaxDepth(int $depth): self;

    /**
     * Set file pattern filter (glob syntax).
     *
     * @param string $pattern Glob pattern (e.g., '*Controller.php')
     *
     * @return self For method chaining
     */
    public function setPattern(string $pattern): self;

    /**
     * Enable or disable symlink following (default: false).
     *
     * When enabled, containment check validates symlink targets
     * resolve within the scanned root directory.
     *
     * @param bool $follow Whether to follow symlinks
     *
     * @return self For method chaining
     */
    public function setFollowSymlinks(bool $follow): self;
}

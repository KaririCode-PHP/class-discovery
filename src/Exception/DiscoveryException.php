<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Exception;

use KaririCode\Contract\Exception\ExceptionInterface;

/**
 * Root of every failure raised by class discovery.
 *
 * Each concrete exception implements exactly one family of
 * kariricode/contract (`NotFound`, `Configuration`, `Runtime`), so an edge
 * maps it to a status without knowing this package; the numeric code keeps
 * failures countable without parsing messages (ARFA 1.3 P5).
 *
 * Codes — kariricode/class-discovery owns 8700–8799:
 *
 * | Code | Exception                         | Family        |
 * |------|-----------------------------------|---------------|
 * | 8701 | PathNotFoundException             | NotFound      |
 * | 8702 | SymlinkEscapeException            | Runtime       |
 * | 8710 | ScanLimitExceededException (depth)| Runtime       |
 * | 8711 | ScanLimitExceededException (files)| Runtime       |
 * | 8720 | CacheIntegrityException           | Runtime       |
 * | 8721 | InsecureCacheDirectoryException   | Configuration |
 * | 8722 | InsecureCacheDirectoryException   | Configuration |
 * | 8730 | CircularDependencyException       | Runtime       |
 * | 8740 | InvalidConfigurationException     | Configuration |
 *
 * ```php
 * try {
 *     $scanner->scan(['src/']);
 * } catch (DiscoveryException $e) {
 *     $e->getCode(); // 8701 when 'src/' does not exist
 * }
 * ```
 *
 * @see docs/spec/SPEC-001-component-specification.md §6
 */
abstract class DiscoveryException extends \RuntimeException implements ExceptionInterface {}

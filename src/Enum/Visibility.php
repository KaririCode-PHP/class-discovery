<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Enum;

/**
 * Member visibility as PHP declares it.
 *
 * Backed by the keyword (E-003): the value is the JSON form of the metadata
 * DPOs (E-001), so `json_encode()` keeps emitting "public", "protected" and
 * "private" — a pure enum could not be encoded at all.
 *
 * ```php
 * $metadata->methods['handle']->visibility === Visibility::Public;
 * ```
 */
enum Visibility: string
{
    case Public = 'public';
    case Protected = 'protected';
    case Private = 'private';
}

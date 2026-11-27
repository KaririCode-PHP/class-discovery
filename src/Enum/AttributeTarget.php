<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Enum;

use Attribute;

/**
 * PHP attribute target classification.
 *
 * Backed enum: int values map to PHP's native Attribute::TARGET_* bitmask.
 * ARFA 1.3 E-003: backed enums permitted when external representation required.
 *
 * ```php
 * AttributeTarget::from(\Attribute::TARGET_METHOD); // AttributeTarget::Method
 * ```
 */
enum AttributeTarget: int
{
    case Class_ = Attribute::TARGET_CLASS;
    case Method = Attribute::TARGET_METHOD;
    case Property = Attribute::TARGET_PROPERTY;
    case Parameter = Attribute::TARGET_PARAMETER;
    case ClassConstant = Attribute::TARGET_CLASS_CONSTANT;
    case All = Attribute::TARGET_ALL;
}

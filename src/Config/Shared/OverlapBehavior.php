<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Config\Shared;

/**
 * Typed enum for the `key_overlap_behavior` wire key consumed by
 * `ConvertLivewireRuleAttributeRector`. Backed string values match the
 * canonical wire-format strings committed in `PUBLIC_API.md`.
 */
enum OverlapBehavior: string
{
    case Bail = 'bail';
    case Partial = 'partial';
}

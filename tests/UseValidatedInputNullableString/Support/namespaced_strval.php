<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableString\Fixture\ShadowedStrval;

/**
 * Shadows PHP's strval() for the fixtures in this namespace, so the rule
 * must resolve the function instead of matching its spelling.
 */
function strval(mixed $value): string
{
    return strtoupper(\strval($value));
}

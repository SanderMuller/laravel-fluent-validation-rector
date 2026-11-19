<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\Source;

/**
 * Numeric case values: for 1.5, enum() returns the One case and
 * tryFrom('1.5') returns null.
 */
enum PostRating: string
{
    case One = '1';
    case Two = '2';
}

<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\Source;

/**
 * A '0' case value: for false, enum() returns the Off case and tryFrom('')
 * returns null.
 */
enum PostFlag: string
{
    case Off = '0';
    case On = '1';
}

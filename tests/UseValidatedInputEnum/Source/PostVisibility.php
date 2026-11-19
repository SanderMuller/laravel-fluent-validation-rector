<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\Source;

/**
 * A blank case value: enum() returns null for a blank field, and tryFrom('')
 * returns the Unset case.
 */
enum PostVisibility: string
{
    case Unset = '';
    case Public = 'public';
}

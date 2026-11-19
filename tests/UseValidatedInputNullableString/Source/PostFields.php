<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableString\Source;

/**
 * Autoloadable constants for the fixtures. Rector's test case does not
 * reflect classes declared inside a fixture when it runs under Pest.
 */
final class PostFields
{
    public const string TITLE = 'title';

    public const string DEFAULT_TITLE = 'untitled';
}

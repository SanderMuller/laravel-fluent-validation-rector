<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableString\Source;

use SanderMuller\FluentValidation\ValidatedInput;

/**
 * A magic property runs code on every read, so a rewrite that reads the
 * receiver once instead of twice can change the result.
 *
 * @property-read ValidatedInput $safe
 */
final readonly class InputHolder
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(private array $data) {}

    public function __get(string $name): ValidatedInput
    {
        return new ValidatedInput([$name => $this->data]);
    }
}

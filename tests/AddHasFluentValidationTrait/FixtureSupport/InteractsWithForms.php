<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\AddHasFluentValidationTrait\FixtureSupport;

/**
 * Stand-in for Filament v3's `Filament\Forms\Concerns\InteractsWithForms`.
 * The real trait declares `validate()`, `validateOnly()`, `getRules()`,
 * `getMessages()`, and `getValidationAttributes()` — all five collide with
 * the main package's `HasFluentValidation` trait as of 1.7.1.
 *
 * This fixture stand-in declares the same method surface so detection logic
 * in `AddHasFluentValidationTraitRector::findFilamentInteractsWithFormsTrait()`
 * can match against it by name substring and the tests don't require a full
 * Filament install.
 */
trait InteractsWithForms
{
    /**
     * @return array{}
     */
    public function validate(array $rules = null, array $messages = [], array $attributes = []): array
    {
        return [];
    }

    /**
     * @return array{}
     */
    public function validateOnly(string $field, array $rules = null, array $messages = [], array $attributes = []): array
    {
        return [];
    }

    /**
     * @return array{}
     */
    public function getRules(): array
    {
        return [];
    }

    /**
     * @return array{}
     */
    public function getMessages(): array
    {
        return [];
    }

    /**
     * @return array{}
     */
    public function getValidationAttributes(): array
    {
        return [];
    }
}

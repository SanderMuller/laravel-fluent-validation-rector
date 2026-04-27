<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Config\Shared;

/**
 * Typed list of FormRequest base-class FQNs consumed by
 * `AddHasFluentRulesTraitRector` (and any future rector that takes a
 * base-class allowlist).
 */
final readonly class BaseClassRegistry
{
    /**
     * @param  list<class-string>  $baseClasses
     */
    private function __construct(public array $baseClasses) {}

    public static function none(): self
    {
        return new self([]);
    }

    /**
     * @param  list<class-string>  $baseClasses
     */
    public static function of(array $baseClasses): self
    {
        return new self($baseClasses);
    }
}

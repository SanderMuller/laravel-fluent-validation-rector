<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Config;

use SanderMuller\FluentValidationRector\Config\Shared\BaseClassRegistry;
use SanderMuller\FluentValidationRector\Rector\AddHasFluentRulesTraitRector;

/**
 * Typed builder for `AddHasFluentRulesTraitRector`'s configuration.
 *
 *     $rectorConfig->ruleWithConfiguration(
 *         AddHasFluentRulesTraitRector::class,
 *         HasFluentRulesTraitOptions::default()
 *             ->withBaseClasses(BaseClassRegistry::of(['App\\Http\\Requests\\BaseRequest']))
 *             ->toArray(),
 *     );
 */
final readonly class HasFluentRulesTraitOptions
{
    private function __construct(public BaseClassRegistry $baseClasses) {}

    public static function default(): self
    {
        return new self(BaseClassRegistry::none());
    }

    public function withBaseClasses(BaseClassRegistry $baseClasses): self
    {
        return new self($baseClasses);
    }

    /**
     * @return array{base_classes: list<class-string>}
     */
    public function toArray(): array
    {
        return [
            AddHasFluentRulesTraitRector::BASE_CLASSES => $this->baseClasses->baseClasses,
        ];
    }
}

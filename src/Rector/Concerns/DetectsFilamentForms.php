<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector\Concerns;

use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use Rector\Rector\AbstractRector;
use ReflectionClass;

/**
 * Detects whether a class is a Filament-integrated Livewire component by
 * scanning its direct trait uses and walking its ancestor chain for
 * `InteractsWithForms` (Filament v3/v4) or `InteractsWithSchemas` (v5).
 *
 * Matched by substring on the trait short-name so aliased imports still
 * trigger detection — the discriminator is the trait name, not the full FQN,
 * because consumers occasionally alias Filament traits via composer autoload
 * or their own wrapper packages.
 *
 * @phpstan-require-extends AbstractRector
 */
trait DetectsFilamentForms
{
    /** @var list<string> */
    private const array FILAMENT_FORM_TRAIT_NEEDLES = [
        'InteractsWithForms',
        'InteractsWithSchemas',
    ];

    private function isFilamentClass(Class_ $class): bool
    {
        if ($this->findDirectFilamentTrait($class) instanceof Name) {
            return true;
        }

        return $this->ancestorHasFilamentTrait($class);
    }

    /**
     * Return the Name node of the first Filament trait used directly on this
     * class (not inherited). Used when emitting the `insteadof` adaptation so
     * the adaptation references the same namespace form the consumer wrote
     * (short alias vs fully-qualified). Returns null if the class has no
     * direct Filament trait.
     */
    private function findDirectFilamentTrait(Class_ $class): ?Name
    {
        foreach ($class->getTraitUses() as $traitUse) {
            foreach ($traitUse->traits as $trait) {
                $name = $this->getName($trait);

                if ($name !== null && $this->nameMatchesFilamentNeedle($name)) {
                    return $trait;
                }
            }
        }

        return null;
    }

    private function classHasFilamentTraitDirectly(Class_ $class): bool
    {
        return $this->findDirectFilamentTrait($class) instanceof Name;
    }

    private function ancestorHasFilamentTrait(Class_ $class): bool
    {
        if (! $class->extends instanceof Name) {
            return false;
        }

        $parentName = $class->extends->toString();

        if (! class_exists($parentName)) {
            return false;
        }

        $reflection = new ReflectionClass($parentName);

        while ($reflection instanceof ReflectionClass) {
            foreach ($reflection->getTraitNames() as $traitName) {
                if ($this->nameMatchesFilamentNeedle($traitName)) {
                    return true;
                }

                if ($this->traitContainsFilamentTrait($traitName)) {
                    return true;
                }
            }

            $parent = $reflection->getParentClass();
            $reflection = $parent === false ? null : $parent;
        }

        return false;
    }

    private function traitContainsFilamentTrait(string $traitName): bool
    {
        if (! trait_exists($traitName) && ! class_exists($traitName)) {
            return false;
        }

        $reflection = new ReflectionClass($traitName);

        foreach ($reflection->getTraitNames() as $nested) {
            if ($this->nameMatchesFilamentNeedle($nested)) {
                return true;
            }

            if ($this->traitContainsFilamentTrait($nested)) {
                return true;
            }
        }

        return false;
    }

    private function nameMatchesFilamentNeedle(string $name): bool
    {
        foreach (self::FILAMENT_FORM_TRAIT_NEEDLES as $needle) {
            if (str_contains($name, $needle)) {
                return true;
            }
        }

        return false;
    }
}

<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector\Concerns;

use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use ReflectionClass;

/**
 * Detects whether a class inherits a given trait from any parent in its
 * class hierarchy.
 *
 * Used by the trait-insertion rectors so they can skip subclasses that already
 * inherit the fluent-validation trait from an intermediate base class (e.g.
 * `BaseCrmAdminCredentials` declaring `use HasFluentValidation;` shared across
 * many Livewire admin subclasses). Without this check the rector re-adds the
 * trait to every subclass — harmless but noisy in inheritance-heavy codebases.
 *
 * Uses native PHP ReflectionClass so the check works regardless of Rector's
 * static-analysis reflection state, provided the parent class is autoloadable
 * in the consumer's composer context (true for Laravel projects where Rector
 * runs with the project's autoloader active). Returns false on reflection
 * failure so the trait still gets added when the hierarchy can't be
 * introspected.
 */
trait DetectsInheritedTraits
{
    private function anyAncestorUsesTrait(Class_ $class, string $traitFqn): bool
    {
        if (! $class->extends instanceof Name) {
            return false;
        }

        $parentName = $class->extends->toString();

        if (! class_exists($parentName) && ! interface_exists($parentName) && ! trait_exists($parentName)) {
            return false;
        }

        return $this->reflectionUsesTrait(new ReflectionClass($parentName), $traitFqn);
    }

    /**
     * @param  ReflectionClass<object>  $reflection
     */
    private function reflectionUsesTrait(ReflectionClass $reflection, string $traitFqn): bool
    {
        $target = ltrim($traitFqn, '\\');

        $current = $reflection;

        while ($current instanceof ReflectionClass) {
            foreach ($current->getTraitNames() as $traitName) {
                if ($traitName === $target) {
                    return true;
                }

                if ($this->traitUsesTrait($traitName, $target)) {
                    return true;
                }
            }

            $parent = $current->getParentClass();
            $current = $parent === false ? null : $parent;
        }

        return false;
    }

    private function traitUsesTrait(string $containingTrait, string $targetTrait): bool
    {
        if (! trait_exists($containingTrait) && ! class_exists($containingTrait)) {
            return false;
        }

        $reflection = new ReflectionClass($containingTrait);

        foreach ($reflection->getTraitNames() as $nested) {
            if ($nested === $targetTrait) {
                return true;
            }

            if ($this->traitUsesTrait($nested, $targetTrait)) {
                return true;
            }
        }

        return false;
    }
}

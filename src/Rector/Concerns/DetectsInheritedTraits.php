<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector\Concerns;

use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use Rector\Rector\AbstractRector;
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
 *
 * Alias-resolving variants `anyAncestorExtends()` and `currentOrAncestorUsesTrait()`
 * live beside the legacy `anyAncestorUsesTrait()`: the legacy path reads
 * `$class->extends->toString()` raw and silently misses aliased imports
 * (`use FormRequest as BaseRequest; class Foo extends BaseRequest`). The new
 * methods go through `$this->getName()` (Rector's `NodeNameResolver`) before
 * feeding names to reflection, so aliased/imported ancestors resolve correctly.
 * They also cover the direct-trait-use-on-current-class case, which the legacy
 * helper early-returns on (requires a parent class to scan). See spec
 * `update-rules-return-type-docblock-rector.md` for the Codex findings that
 * motivated these additions.
 *
 * @phpstan-require-extends AbstractRector
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

    /**
     * Alias-aware ancestor-extends check. Returns true if `$class` is, or any
     * ancestor in its class hierarchy is, the class identified by `$targetFqn`.
     *
     * `$class->extends` is resolved through `$this->getName(...)` before the
     * reflection walk so aliased imports (`use FormRequest as BaseRequest`)
     * succeed: `getName()` reads the `resolvedName` attribute set by Rector's
     * `NodeNameResolver`, not the raw literal token. The raw `toString()`
     * returns the alias (`BaseRequest`) which fails `class_exists`, aborting
     * the walk before ever reaching the real parent. Target FQN is also
     * normalized to ltrim-`\\` so callers can pass either `Foo\Bar` or
     * `\Foo\Bar`.
     */
    private function anyAncestorExtends(Class_ $class, string $targetFqn): bool
    {
        if (! $class->extends instanceof Name) {
            return false;
        }

        $parentName = $this->getName($class->extends);

        if ($parentName === null || (! class_exists($parentName) && ! interface_exists($parentName))) {
            return false;
        }

        return $this->reflectedAncestryExtends($parentName, $targetFqn);
    }

    /**
     * Pure reflection walk extracted so unit tests can drive it with a
     * resolved FQN directly, without building a full Rector harness.
     */
    private function reflectedAncestryExtends(string $className, string $targetFqn): bool
    {
        if (! class_exists($className) && ! interface_exists($className)) {
            return false;
        }

        $target = ltrim($targetFqn, '\\');
        /** @var class-string $className */
        $current = new ReflectionClass($className);

        while ($current instanceof ReflectionClass) {
            if (ltrim($current->getName(), '\\') === $target) {
                return true;
            }

            $parent = $current->getParentClass();
            $current = $parent === false ? null : $parent;
        }

        return false;
    }

    /**
     * Returns true if `$class` itself uses `$traitFqn` (via an AST `TraitUse`
     * node directly on the class body) OR if any ancestor in its class
     * hierarchy uses it (reflection-walked via `anyAncestorUsesTrait`'s
     * existing logic).
     *
     * Covers the emit-path's motivating case: non-FormRequest classes that
     * directly `use HasFluentRules;` with no parent class at all. The legacy
     * `anyAncestorUsesTrait()` short-circuits false when `$class->extends` is
     * not a `Name`, silently skipping every parentless-class-with-trait target.
     *
     * Trait names inside the `TraitUse` node are resolved through
     * `$this->getName(...)` so `use HasFluentRules as Fluent;` aliasing still
     * matches (Rector's `NodeNameResolver` sees through the alias to the
     * original FQN).
     */
    private function currentOrAncestorUsesTrait(Class_ $class, string $traitFqn): bool
    {
        $target = ltrim($traitFqn, '\\');

        foreach ($class->getTraitUses() as $traitUse) {
            foreach ($traitUse->traits as $traitName) {
                $resolved = $this->getName($traitName);

                if ($resolved === null) {
                    continue;
                }

                if (ltrim($resolved, '\\') === $target) {
                    return true;
                }

                if ($this->traitUsesTrait($resolved, $target)) {
                    return true;
                }
            }
        }

        return $this->aliasAwareAncestorUsesTrait($class, $traitFqn);
    }

    /**
     * Walks `$class`'s ancestor chain looking for any class that uses
     * `$traitFqn`. Resolves `$class->extends` through `$this->getName(...)`
     * before reflection — the legacy `anyAncestorUsesTrait` reads
     * `$class->extends->toString()` raw, which returns the alias token
     * (`BaseAlias`) for `use App\BaseComponent as BaseAlias; class Foo extends BaseAlias`
     * and fails `class_exists`, aborting the walk before reaching the real
     * parent that may use the trait. Codex adversarial review flagged this as
     * a silent false-negative on inheritance-heavy Livewire/trait setups.
     */
    private function aliasAwareAncestorUsesTrait(Class_ $class, string $traitFqn): bool
    {
        if (! $class->extends instanceof Name) {
            return false;
        }

        $parentName = $this->getName($class->extends);

        if ($parentName === null || (! class_exists($parentName) && ! interface_exists($parentName) && ! trait_exists($parentName))) {
            return false;
        }

        return $this->reflectionUsesTrait(new ReflectionClass($parentName), $traitFqn);
    }
}

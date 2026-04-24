<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector\Concerns;

use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use Rector\Rector\AbstractRector;
use ReflectionClass;
use SanderMuller\FluentValidation\HasFluentValidation;
use SanderMuller\FluentValidation\HasFluentValidationForFilament;

/**
 * Identify Livewire classes by ancestry, not by surface shape.
 *
 * A class counts as Livewire when any of:
 *  - Its parent chain (resolved via `$this->getName()` so aliased imports work)
 *    reaches `Livewire\Component` or `Livewire\Form`.
 *  - It already uses `HasFluentValidation` / `HasFluentValidationForFilament`
 *    directly on the class body — those traits are Livewire-only.
 *
 * Rationale: the previous heuristic matched any class with a `render()`
 * method. That misfires on `CustomRenderInterface::render()` on Exceptions,
 * `PaginatorContract::render()` on DataObjects, action-class `render()`
 * methods, Blade view components, Nova tools, etc. Confirmed against a real
 * codebase: 70 false positives out of 519 skip-log entries.
 *
 * Ancestry-based detection also correctly handles intermediate base classes
 * (`MyComponent extends BaseComponent extends Livewire\Component`) and
 * Filament pages (which extend `Livewire\Component` transitively).
 *
 * Falls back to false on reflection failure so the guarded rector can still
 * run when the consumer's autoloader doesn't know about the parent class.
 *
 * @phpstan-require-extends AbstractRector
 */
trait IdentifiesLivewireClasses
{
    /**
     * Livewire base classes that mark a class as a Livewire component/form.
     *
     * @var list<string>
     */
    private const array LIVEWIRE_BASE_CLASSES = [
        'Livewire\Component',
        'Livewire\Form',
    ];

    private function isLivewireClass(Class_ $class): bool
    {
        foreach ($class->getTraitUses() as $traitUse) {
            foreach ($traitUse->traits as $trait) {
                $resolved = $this->getName($trait);

                if ($resolved === HasFluentValidation::class
                    || $resolved === HasFluentValidationForFilament::class) {
                    return true;
                }
            }
        }

        if (! $class->extends instanceof Name) {
            return false;
        }

        $parentName = $this->getName($class->extends);

        if ($parentName === null) {
            return false;
        }

        if (in_array($parentName, self::LIVEWIRE_BASE_CLASSES, true)) {
            return true;
        }

        if (! class_exists($parentName)) {
            return false;
        }

        return $this->ancestryReachesLivewire($parentName);
    }

    private function ancestryReachesLivewire(string $className): bool
    {
        /** @var class-string $className */
        $current = new ReflectionClass($className);

        while ($current instanceof ReflectionClass) {
            if (in_array($current->getName(), self::LIVEWIRE_BASE_CLASSES, true)) {
                return true;
            }

            $parent = $current->getParentClass();
            $current = $parent === false ? null : $parent;
        }

        return false;
    }
}

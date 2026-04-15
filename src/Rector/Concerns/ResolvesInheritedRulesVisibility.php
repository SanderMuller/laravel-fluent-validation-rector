<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector\Concerns;

use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use Rector\Rector\AbstractRector;
use ReflectionClass;

/**
 * Resolve the correct visibility modifier for a generated `rules()` method by
 * walking the class's ancestor chain via `ReflectionClass`.
 *
 * PHP's rules for overriding inherited methods:
 *
 * - If any ancestor declares `rules()` as `final`, the subclass CANNOT
 *   override it. Emitting a `rules()` method produces a fatal at class-load
 *   time (`Cannot override final method`). Return null so the caller can
 *   bail with a skip-log entry rather than ship broken output.
 * - Visibility can be widened but not narrowed. If an ancestor has
 *   `public rules()`, the subclass must also be `public` — narrowing to
 *   `protected` is a fatal covariance violation. Return `MODIFIER_PUBLIC`.
 * - No ancestor `rules()` → default to `MODIFIER_PROTECTED` (Livewire
 *   invokes `rules()` from validator machinery internally, external
 *   visibility is not useful).
 *
 * Reflects on the parent class via the AST's `$class->extends` Name rather
 * than the child itself — the child is typically parsed from a file that
 * isn't autoloadable at rector-time, while the parent (vendor/, project
 * src/) is expected to be. When the parent can't be autoloaded, fall back
 * to `MODIFIER_PROTECTED`; the covariance check at actual class-load time
 * would surface any real conflict.
 *
 * @phpstan-require-extends AbstractRector
 */
trait ResolvesInheritedRulesVisibility
{
    private function resolveGeneratedRulesVisibility(Class_ $class): ?int
    {
        if (! $class->extends instanceof Name) {
            return Class_::MODIFIER_PROTECTED;
        }

        $parentName = $this->getName($class->extends);

        if ($parentName === null || ! class_exists($parentName)) {
            return Class_::MODIFIER_PROTECTED;
        }

        return $this->walkAncestorChainForRulesVisibility(new ReflectionClass($parentName));
    }

    /**
     * @param  ReflectionClass<object>  $parent
     */
    private function walkAncestorChainForRulesVisibility(ReflectionClass $parent): ?int
    {
        do {
            if ($parent->hasMethod('rules')) {
                $parentRules = $parent->getMethod('rules');

                if ($parentRules->isFinal()) {
                    return null;
                }

                if ($parentRules->isPublic()) {
                    return Class_::MODIFIER_PUBLIC;
                }

                return Class_::MODIFIER_PROTECTED;
            }

            $next = $parent->getParentClass();

            if ($next === false) {
                return Class_::MODIFIER_PROTECTED;
            }

            $parent = $next;
        } while (true);
    }
}

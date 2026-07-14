<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector\Concerns;

use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use SanderMuller\FluentValidation\FluentRules;
use SanderMuller\FluentValidation\FluentSchema;

/**
 * Reflection-only inspection of a class's rules()/schema() surface, used by
 * `ConvertToFluentSchemaRector`'s parent-conversion probe. Pure reflection: no
 * AST, no name resolver, no rule-pipeline state — so it stays a small, testable
 * unit and keeps the rector's own class cognitive-complexity in check.
 *
 * @internal
 */
trait InspectsReflectedRuleSurface
{
    /**
     * Whether `$parentName`'s nearest schema()/rules() surface is a
     * `schema(FluentSchema)` builder with NO rules() resolvable anywhere on it —
     * declared OR inherited. That is the ONLY case the child's
     * `parent::rules()` → `parent::schema()` rewrite is unconditionally provable:
     * `parent::rules()` genuinely strands (no rules() to resolve), and
     * `parent::schema()` binds to that schema-only base. It's a concrete on-disk
     * fact, so it holds regardless of this run's file set — the case a re-run
     * over a partly-converted chain needs to converge a stranded child, which the
     * rules()-declarer walk can't see (it strides past a base that declares no
     * rules()).
     *
     * Returns false — deferring to the caller's rules()-chain walk — for every
     * other shape, INCLUDING a schema() builder that still has a resolvable
     * (inherited) rules(): there `parent::rules()` resolves to the rules() owner,
     * so the walk correctly rewrites the child only when that owner itself
     * converts (else it leaves `parent::rules()` intact). `hasMethod` (not
     * declared-only) is what distinguishes the two: an inherited rules() keeps
     * the call resolvable and must NOT be treated as schema-only.
     */
    private function parentIsSchemaOnlyBuilder(string $parentName): bool
    {
        if (! class_exists($parentName)) {
            return false;
        }

        // class_exists() above guarantees the constructor resolves, so no catch
        // is needed (mirrors DetectsInheritedTraits / ParsesParentRulesMethod).
        $current = new ReflectionClass($parentName);

        while ($current instanceof ReflectionClass) {
            if ($this->reflectionClassDeclaresSchemaBuilder($current)) {
                return ! $current->hasMethod('rules');
            }

            if ($this->reflectionClassDeclaresMethod($current, 'rules')) {
                return false;
            }

            $parent = $current->getParentClass();
            $current = $parent === false ? null : $parent;
        }

        return false;
    }

    /**
     * Whether `$class` DECLARES a `schema()` method that is the FluentSchema
     * builder hook — public, non-static, first parameter typed
     * {@see FluentSchema} — mirroring the runtime's own `schemaExpectsFluentSchema()`
     * dispatch gate. An unrelated `schema()` (no FluentSchema parameter) is not a
     * builder and must not count.
     *
     * @param  ReflectionClass<object>  $class
     */
    private function reflectionClassDeclaresSchemaBuilder(ReflectionClass $class): bool
    {
        if (! $this->reflectionClassDeclaresMethod($class, 'schema')) {
            return false;
        }

        $method = $class->getMethod('schema');

        if (! $method->isPublic() || $method->isStatic()) {
            return false;
        }

        $parameters = $method->getParameters();

        if ($parameters === []) {
            return false;
        }

        $firstType = $parameters[0]->getType();

        return $firstType instanceof ReflectionNamedType
            && $firstType->getName() === FluentSchema::class;
    }

    /**
     * Whether `$class` DECLARES `$method` in its own body (not merely inherits
     * it), mirroring the converter's AST-level `Class_::getMethod()` guard —
     * `ReflectionClass::hasMethod()` alone also reports inherited methods.
     *
     * @param  ReflectionClass<object>  $class
     */
    private function reflectionClassDeclaresMethod(ReflectionClass $class, string $method): bool
    {
        return $class->hasMethod($method)
            && $class->getMethod($method)->getDeclaringClass()->getName() === $class->getName();
    }

    private function reflectionMethodHasFluentRulesAttribute(ReflectionMethod $method): bool
    {
        return $method->getAttributes(FluentRules::class) !== [];
    }
}

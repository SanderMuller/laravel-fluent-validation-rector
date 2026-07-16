<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector\Concerns;

use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use SanderMuller\FluentValidation\FluentRules;
use SanderMuller\FluentValidation\FluentSchema;
use SanderMuller\FluentValidation\HasFluentRules;

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
     * Memoized {@see hasFluentRulesTraitFile()} result. The separate resolved
     * flag disambiguates "not looked up yet" from "looked up, trait unlocatable"
     * (both null).
     */
    private ?string $hasFluentRulesTraitFileCache = null;

    private bool $hasFluentRulesTraitFileResolved = false;

    /**
     * Whether `$parentName`'s nearest schema()/rules() surface is a
     * `schema(FluentSchema)` builder with NO *real* rules() resolvable anywhere
     * on it — declared OR inherited. That is the ONLY case the child's
     * `parent::rules()` → `parent::schema()` rewrite is unconditionally provable:
     * `parent::rules()` resolves to nothing a hand-written base declared, and
     * `parent::schema()` binds to that schema-only base. It's a concrete on-disk
     * fact, so it holds regardless of this run's file set — the case a re-run
     * over a partly-converted chain needs to converge a stranded child, which the
     * rules()-declarer walk can't see (it strides past a base that declares no
     * rules()).
     *
     * "*Real*" rules() is the subtlety: from laravel-fluent-validation 1.33,
     * `HasFluentRules` ships a fallback `rules()` that just re-exposes `schema()`,
     * so `hasMethod('rules')` is true on EVERY trait user — a plain
     * `! hasMethod('rules')` would stop recognizing a converted schema-only base
     * (regressing Fix B: the child would be left stranded on re-run). The
     * fallback lives in the trait's own file, so {@see classResolvesRealRulesMethod()}
     * discounts it and counts only a rules() declared elsewhere. On <1.33 (no
     * fallback) a schema-only base has no rules() at all, so the check is correct
     * across both versions.
     *
     * Returns false — deferring to the caller's rules()-chain walk — for every
     * other shape, INCLUDING a schema() builder that still has a resolvable REAL
     * (inherited) rules(): there `parent::rules()` resolves to the rules() owner,
     * so the walk correctly rewrites the child only when that owner itself
     * converts (else it leaves `parent::rules()` intact). Counting only a real
     * rules() is what distinguishes the two: a real inherited rules() keeps the
     * call resolvable and must NOT be treated as schema-only.
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
                return ! $this->classResolvesRealRulesMethod($current);
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
     * Whether `$class` resolves a REAL rules() method — one a consumer or a
     * framework base declared — as opposed to the no-op fallback `HasFluentRules`
     * ships from 1.33 (which just re-exposes `schema()`). The fallback's body
     * lives in the trait's own file, so a rules() whose declaring file is any
     * other file is a genuine rule source; one resolving to the trait file is the
     * fallback and doesn't count. On <1.33 a schema-only base has no rules() at
     * all (`hasMethod` false), so this returns false there too.
     *
     * @param  ReflectionClass<object>  $class
     */
    private function classResolvesRealRulesMethod(ReflectionClass $class): bool
    {
        if (! $class->hasMethod('rules')) {
            return false;
        }

        $rulesFile = $class->getMethod('rules')->getFileName();

        return $rulesFile !== false && $rulesFile !== $this->hasFluentRulesTraitFile();
    }

    /**
     * Absolute path to the `HasFluentRules` trait source, or null when it can't
     * be located. The 1.33+ fallback rules() is declared there; comparing a
     * resolved rules()'s file against it is what tells the fallback apart from a
     * real declaration. Memoized for the run. Null (an unlocatable trait) makes
     * {@see classResolvesRealRulesMethod()} treat every rules() as real — the
     * pre-1.33 behavior — which is the safe default.
     */
    private function hasFluentRulesTraitFile(): ?string
    {
        if ($this->hasFluentRulesTraitFileResolved) {
            return $this->hasFluentRulesTraitFileCache;
        }

        $this->hasFluentRulesTraitFileResolved = true;

        if (trait_exists(HasFluentRules::class)) {
            $file = (new ReflectionClass(HasFluentRules::class))->getFileName();
            $this->hasFluentRulesTraitFileCache = $file === false ? null : $file;
        }

        return $this->hasFluentRulesTraitFileCache;
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

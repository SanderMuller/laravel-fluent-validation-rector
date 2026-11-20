<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector\Concerns;

use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Return_;
use Rector\Rector\AbstractRector;
use ReflectionClass;
use SanderMuller\FluentValidation\HasFluentRules;
use Throwable;

/**
 * Ancestor walk of `ConvertToFluentSchemaRector`: proves whether the rules()
 * an ancestor declares converts to schema() as well. The rector needs this for
 * a `parent::rules()` call, and for a class whose inherited rules() would
 * answer `$request->rules()` once the class's own method is renamed.
 *
 * @internal
 *
 * @phpstan-require-extends AbstractRector
 */
trait ProvesAncestorSchemaConversion
{
    /**
     * Whether an ancestor resolves a real rules() (not the HasFluentRules
     * fallback) that would stay in place after this class's rename. Reuses the
     * parent-conversion walk: an ancestor that provably converts drops its
     * rules() too, so the fallback answers again.
     */
    private function inheritedRulesWouldShadowSchema(Class_ $class): bool
    {
        if (! $class->extends instanceof Name) {
            return false;
        }

        $parentName = $this->getName($class->extends);

        if ($parentName === null || ! class_exists($parentName)) {
            return false;
        }

        if (! $this->classResolvesRealRulesMethod(new ReflectionClass($parentName))) {
            return false;
        }

        return ! $this->parentSchemaConversionIsProvable($class);
    }

    /**
     * Skip reason for {@see inheritedRulesWouldShadowSchema()}. Names the nearest
     * ancestor that declares rules(), because that one answers `$request->rules()`
     * after the rename. When its body is exactly `return [];`, the fix is to
     * delete it, so the reason says that.
     */
    private function inheritedRulesShadowSkipReason(Class_ $class): string
    {
        $parentName = $class->extends instanceof Name ? $this->getName($class->extends) : null;
        $ancestor = null;

        try {
            $ancestor = $parentName === null ? null : $this->findDeclaringClassForRules($parentName);
        } catch (Throwable) {
        }

        if (! $ancestor instanceof ReflectionClass) {
            return 'an ancestor declares its own rules() that does not convert to schema() — after the rename, $request->rules() would return the ancestor\'s rules instead of this class\'s. Fix the ancestor first, or leave this class on rules().';
        }

        $ancestorName = $ancestor->getName();
        $fileName = $ancestor->getFileName();
        $method = $fileName === false ? null : $this->loadParentRulesMethod($fileName, $ancestorName);

        if ($method instanceof ClassMethod && $this->isEmptyArrayReturnBody($method)) {
            return sprintf(
                '%s::rules() returns an empty array and does not convert to schema() — after the rename, $request->rules() would return that empty array instead of this class\'s rules. Remove the empty rules() from %s to unblock its subclasses, or leave this class on rules().',
                $ancestorName,
                $ancestorName,
            );
        }

        return sprintf(
            '%s declares its own rules() that does not convert to schema() — after the rename, $request->rules() would return its rules instead of this class\'s. Convert %s first (add #[FluentRules] to the rules() of an abstract base that builds FluentRule chains), or leave this class on rules().',
            $ancestorName,
            $ancestorName,
        );
    }

    private function isEmptyArrayReturnBody(ClassMethod $method): bool
    {
        $stmts = $method->stmts ?? [];

        return count($stmts) === 1
            && $stmts[0] instanceof Return_
            && $stmts[0]->expr instanceof Array_
            && $stmts[0]->expr->items === [];
    }

    /**
     * Whether the class `parent::rules()` resolves to will itself be renamed to
     * schema(), so rewriting the child's call to `parent::schema()` lands on a
     * real method. That holds only when the WHOLE ancestor rules()-chain
     * converts, so this walks it: for each ancestor that declares rules() it
     * re-applies every converter gate (see {@see reflectedRulesClassConverts()}).
     * A level carrying #[FluentRules], or a chain root whose rules() doesn't call
     * parent::rules(), terminates the walk as converting; a level that itself
     * calls parent::rules() converts only if ITS parent does, so the walk
     * continues upward. Fails closed on any unresolvable or non-converting link.
     * Native reflection resolves each ancestor FQN (reliable, unlike a parsed
     * node's un-resolved `extends` name); the rules() body is parsed per level.
     */
    private function parentSchemaConversionIsProvable(Class_ $class): bool
    {
        if (! $class->extends instanceof Name) {
            return false;
        }

        $parentName = $this->getName($class->extends);

        if ($parentName === null) {
            return false;
        }

        // A base already converted to a schema(FluentSchema)-ONLY builder (an
        // earlier SCHEMA pass renamed its rules() away, with no rules() resolvable
        // anywhere on it) makes parent::schema() resolve and parent::rules() NOT.
        // The child MUST rewrite or it strands — and this is the case the
        // rules()-declarer walk below can't see (it strides past a base declaring
        // no rules()), so a re-run over a partly-converted chain never converged
        // the stranded child before this check. It's a concrete on-disk fact,
        // independent of this run's file set. Every other shape (including a
        // schema() base that still inherits a rules()) falls through to the walk,
        // which converts the child only when the resolvable rules() owner itself
        // converts and otherwise leaves parent::rules() intact.
        if ($this->parentIsSchemaOnlyBuilder($parentName)) {
            return true;
        }

        $seen = [];

        while ($parentName !== null && class_exists($parentName)) {
            $declaringClass = $this->findDeclaringClassForRules($parentName);

            if (! $declaringClass instanceof ReflectionClass || isset($seen[$declaringClass->getName()])) {
                return false;
            }

            $seen[$declaringClass->getName()] = true;

            $verdict = $this->reflectedRulesClassConverts($declaringClass);

            if ($verdict !== null) {
                return $verdict;
            }

            // null: this level converts iff its own parent does — walk up.
            $parent = $declaringClass->getParentClass();
            $parentName = $parent === false ? null : $parent->getName();
        }

        return false;
    }

    /**
     * Per-level verdict for a class that DECLARES rules(): true = converts
     * (terminal), false = won't convert (terminal), null = converts iff its own
     * parent does (the caller keeps walking upward).
     *
     * Applies the converter's gates — reflection for what reflection sees (uses
     * HasFluentRules, no DECLARED schema() duplicate, public non-static
     * parameterless rules(), abstract → #[FluentRules]) and a parse of the
     * actual rules() body for `methodBodyIsSafeToInjectBuilder()`, which catches
     * the string-only / dynamic / unsafe-closure bodies reflection can't.
     *
     * @param  ReflectionClass<object>  $declaringClass
     */
    private function reflectedRulesClassConverts(ReflectionClass $declaringClass): ?bool
    {
        $rulesMethod = $declaringClass->getMethod('rules');

        if (! $this->reflectionUsesTrait($declaringClass, HasFluentRules::class)
            || $this->reflectionClassDeclaresMethod($declaringClass, 'schema')
            || ! $rulesMethod->isPublic()
            || $rulesMethod->isStatic()
            || $rulesMethod->getNumberOfParameters() !== 0) {
            return false;
        }

        $optedIn = $this->reflectionMethodHasFluentRulesAttribute($rulesMethod);

        if ($declaringClass->isAbstract() && ! $optedIn) {
            return false;
        }

        $fileName = $declaringClass->getFileName();

        if ($fileName === false) {
            return false;
        }

        // Parse the whole class (name-resolved, so an aliased/FQN FluentRule
        // import — `use ...FluentRule as R; R::string()` — still matches by FQN).
        $parsedClass = $this->loadParentRulesClass($fileName, $declaringClass->getName(), resolveNames: true);

        if (! $parsedClass instanceof Class_) {
            return false;
        }

        $parsedMethod = $parsedClass->getMethod('rules');

        // Body-level gates reflection can't see: the rules() body must be safe to
        // inject a builder into, AND no OTHER method may call the class's own
        // rules() — the converter bails such a class via
        // hasSelfRulesCallOutsideMethod(), leaving it as rules().
        if (! $parsedMethod instanceof ClassMethod
            || ! $this->methodBodyIsSafeToInjectBuilder($parsedMethod)
            || $this->hasSelfRulesCallOutsideMethod($parsedClass, $parsedMethod)) {
            return false;
        }

        // #[FluentRules] asserts convertibility; a root that doesn't call
        // parent::rules() converts on its own body. Otherwise defer to the
        // parent (null → keep walking).
        if ($optedIn || ! $this->bodyCallsParentRules($parsedMethod)) {
            return true;
        }

        return null;
    }
}

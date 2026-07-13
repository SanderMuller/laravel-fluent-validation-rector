<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector\Concerns;

use PhpParser\Node;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use Rector\Rector\AbstractRector;
use ReflectionClass;
use Throwable;

/**
 * Resolves the AST of a parent class's declared `rules()` method by walking the
 * reflected parent chain to the declaring class and parsing its source file.
 *
 * Native `ReflectionClass` (not PHPStan scope) locates the declaring class: the
 * consumer's child may live in a file PHPStan hasn't scoped (rector dry-run
 * against a not-yet-autoloaded path, or a fixture `.inc`), and only the PARENT
 * needs to be loadable — true for FormRequest bases under standard PSR-4. The
 * parsed method is cached by (path, mtime, fqcn) so repeated children sharing a
 * parent pay the parse cost once, and cache misses re-parse when a parent file
 * changes between runs.
 *
 * @internal
 *
 * @phpstan-require-extends AbstractRector
 */
trait ParsesParentRulesMethod
{
    /**
     * @var array<string, Class_|false>
     */
    private static array $parentRulesClassCache = [];

    /**
     * The parsed `rules()` ClassMethod declared on `$class`'s nearest ancestor
     * that declares one, or null when there is no resolvable parent rules().
     */
    private function resolveParentRulesMethod(Class_ $class): ?ClassMethod
    {
        if (! $class->extends instanceof Name) {
            return null;
        }

        $parentFqcn = $this->getName($class->extends);

        if ($parentFqcn === null) {
            return null;
        }

        try {
            $declaringClass = $this->findDeclaringClassForRules($parentFqcn);
        } catch (Throwable) {
            return null;
        }

        if (! $declaringClass instanceof ReflectionClass) {
            return null;
        }

        $fileName = $declaringClass->getFileName();

        if ($fileName === false || $fileName === null) {
            return null;
        }

        return $this->loadParentRulesMethod($fileName, $declaringClass->getName());
    }

    /**
     * Walk the parent chain from `$parentFqcn` upward and return the first
     * class that declares (not inherits) a `rules()` method. Returns null
     * when no class in the chain declares it.
     *
     * @return ReflectionClass<object>|null
     */
    private function findDeclaringClassForRules(string $parentFqcn): ?ReflectionClass
    {
        if (! class_exists($parentFqcn) && ! interface_exists($parentFqcn)) {
            return null;
        }

        $current = new ReflectionClass($parentFqcn);

        while ($current !== false) {
            if (! $current->hasMethod('rules')) {
                $current = $current->getParentClass();

                continue;
            }

            $method = $current->getMethod('rules');

            if ($method->getDeclaringClass()->getName() === $current->getName()) {
                return $current;
            }

            return $method->getDeclaringClass();
        }

        return null;
    }

    /**
     * Walk top-level statements, tracking the active namespace, and return the
     * `Class_` whose fully-qualified name (namespace + short name) matches
     * `$fqcn`. Files can legally declare multiple `namespace Foo { ... }` blocks
     * with same-short-name classes; a short-name-only search would return the
     * first and silently pick the wrong `rules()` body.
     *
     * @param  array<Node>  $stmts
     */
    private function findClassByFqcn(array $stmts, string $fqcn): ?Class_
    {
        return $this->matchClassInStmts($stmts, null, $fqcn);
    }

    /**
     * @param  array<Node>  $stmts
     */
    private function matchClassInStmts(array $stmts, ?string $currentNamespace, string $fqcn): ?Class_
    {
        foreach ($stmts as $stmt) {
            if ($stmt instanceof Namespace_) {
                $nsName = $stmt->name instanceof Name ? $stmt->name->toString() : null;
                $found = $this->matchClassInStmts($stmt->stmts, $nsName, $fqcn);

                if ($found instanceof Class_) {
                    return $found;
                }

                continue;
            }

            if (! $stmt instanceof Class_) {
                continue;
            }

            if (! $stmt->name instanceof Identifier) {
                continue;
            }

            $short = $stmt->name->toString();
            $candidate = $currentNamespace === null
                ? $short
                : $currentNamespace . '\\' . $short;

            if ($candidate === $fqcn) {
                return $stmt;
            }
        }

        return null;
    }

    /**
     * Parse `$fileName`, locate the class whose FQCN matches `$fqcn`, and return
     * its `Class_` node. Matching by FQCN rather than short name guards
     * single-file multi-namespace layouts where two classes with the same short
     * name declare differing bodies. Caches by (path, mtime, fqcn, resolve-names).
     *
     * With `$resolveNames`, the parsed AST is run through nikic's NameResolver
     * so imports and aliases resolve to FQNs (`use X\FluentRule as R; R::m()` →
     * `X\FluentRule::m()`). A caller that inspects the body for a specific class
     * by FQN needs this; the raw parse would leave the alias token unmatched.
     * Callers that inline the body verbatim (keeping short names) leave it off.
     */
    private function loadParentRulesClass(string $fileName, string $fqcn, bool $resolveNames = false): ?Class_
    {
        $mtime = @filemtime($fileName);

        if ($mtime === false) {
            return null;
        }

        $cacheKey = $fileName . ':' . $mtime . ':' . $fqcn . ':' . ($resolveNames ? 'r' : 'u');

        if (array_key_exists($cacheKey, self::$parentRulesClassCache)) {
            $cached = self::$parentRulesClassCache[$cacheKey];

            return $cached === false ? null : $cached;
        }

        $source = @file_get_contents($fileName);

        if ($source === false) {
            self::$parentRulesClassCache[$cacheKey] = false;

            return null;
        }

        $parser = (new ParserFactory())->createForNewestSupportedVersion();

        try {
            $stmts = $parser->parse($source);
        } catch (Throwable) {
            self::$parentRulesClassCache[$cacheKey] = false;

            return null;
        }

        if ($stmts === null) {
            self::$parentRulesClassCache[$cacheKey] = false;

            return null;
        }

        if ($resolveNames) {
            $traverser = new NodeTraverser();
            $traverser->addVisitor(new NameResolver());
            $stmts = $traverser->traverse($stmts);
        }

        $class = $this->findClassByFqcn($stmts, $fqcn);
        self::$parentRulesClassCache[$cacheKey] = $class instanceof Class_ ? $class : false;

        return $class instanceof Class_ ? $class : null;
    }

    /**
     * The parsed `rules()` ClassMethod that `$fqcn` declares, or null. Thin
     * wrapper over {@see loadParentRulesClass()}.
     */
    private function loadParentRulesMethod(string $fileName, string $fqcn, bool $resolveNames = false): ?ClassMethod
    {
        $method = $this->loadParentRulesClass($fileName, $fqcn, $resolveNames)?->getMethod('rules');

        return $method instanceof ClassMethod ? $method : null;
    }
}

<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector\Concerns;

use FilesystemIterator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Factory;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Return_;
use PHPStan\Type\ObjectType;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\FluentRules;
use SplFileInfo;
use UnexpectedValueException;

/**
 * Shared infrastructure for validation-to-FluentRule Rector rules.
 *
 * Provides context detection (FormRequest, $request->validate, Validator::make)
 * and modifier building (string → method call conversion).
 *
 * ─── Design notes: unsafe-parent detection (three layers) ────────────────
 *
 * When a child FormRequest calls `parent::rules()` and then manipulates the
 * result (`array_search`, `array_merge`, bracket assignment, `collect()->merge*()`),
 * converting the PARENT's `rules()` to return FluentRule objects breaks the
 * child: the array-manipulation functions expect plain arrays, not rule
 * objects. So we need to detect these relationships and skip the PARENT.
 *
 * The problem is non-local — the signal lives in the CHILD file, but the
 * decision applies to the PARENT file. Worse, Rector can run workers in
 * parallel (`--parallel`), so worker A may reach the parent before worker B
 * has seen the child. Three detection layers close this race:
 *
 * 1. **In-memory cache** (`$unsafeParentClasses`) — populated by
 *    `collectUnsafeParentClassesFromFile()` when we visit any file. Cheapest
 *    path; handles the common case where parent and child live in the same
 *    file or are visited in order by the same worker.
 *
 * 2. **Shared temp file** (`sys_get_temp_dir()/rector-fluent-unsafe-parents-{hash}.txt`,
 *    hash is xxh128 of `getcwd()`) — cross-worker communication via `flock()`.
 *    When layer 1 misses, each worker reads this file before deciding whether
 *    a parent is unsafe; when a worker detects a new unsafe child, it appends
 *    the parent FQCN here. Scoped per-project via the cwd hash to avoid
 *    collisions between concurrent Rector runs in different repos.
 *
 * 3. **Project-wide filesystem scan** (`scanProjectForUnsafeParents()`) —
 *    final fallback. When a worker is about to convert a non-final class and
 *    both earlier layers missed, it does a one-time `RecursiveDirectoryIterator`
 *    walk of the project root (excluding `vendor/`, `node_modules/`, hidden
 *    dirs), parsing each PHP file's `extends` + manipulation patterns and
 *    populating the unsafe set. Cached per worker via `$filesystemScanDone`
 *    so the cost amortizes across the remaining files in that worker's queue.
 *    This layer closes the race when the child lives in a non-adjacent
 *    directory subtree that the worker hasn't visited yet.
 *
 * Layer 3 is the expensive one (reads every PHP file in the project). But it
 * only runs once per worker, only when layers 1 and 2 miss, and only when the
 * current file is actually a conversion candidate. In practice the scan
 * completes in sub-second time for typical Laravel apps.
 */
trait ConvertsValidationRules
{
    use LogsSkipReasons;

    /**
     * In-memory cache of parent class FQCNs that are unsafe to convert (subclass
     * calls parent::rules() with array manipulation). Populated from both file-level
     * AST scanning and the shared temp file (for cross-process parallel support).
     *
     * @var array<string, true>
     */
    private static array $unsafeParentClasses = [];

    /** @var array<string, string> */
    private const array TYPE_MAP = [
        'email' => 'email',
        'string' => 'string',
        'numeric' => 'numeric',
        'integer' => 'integer',
        'boolean' => 'boolean',
        'bool' => 'boolean',
        'int' => 'integer',
        'array' => 'array',
        'date' => 'date',
        'file' => 'file',
        'image' => 'image',
        'url' => 'url',
        'uuid' => 'uuid',
        'ulid' => 'ulid',
        'ip' => 'ip',
        'password' => 'password',
    ];

    /** @var list<string> */
    private const array SIMPLE_MODIFIERS = [
        'required',
        'nullable',
        'sometimes',
        'filled',
        'present',
        'missing',
        'prohibited',
        'accepted',
        'declined',
        'confirmed',
        'bail',
        'distinct',
        'lowercase',
        'uppercase',
        'json',
        'timezone',
        'exclude',
        'alpha',
        'alphaDash',
        'alphaNumeric',
        'ascii',
        'activeUrl',
        'ipv4',
        'ipv6',
        'macAddress',
        'hexColor',
        'currentPassword',
        'list',
    ];

    /** @var list<string> */
    private const array NUMERIC_ARG_RULES = [
        'min',
        'max',
        'exactly',
        'digits',
        'multipleOf',
    ];

    /** @var list<string> */
    private const array TWO_NUMERIC_ARG_RULES = [
        'between',
        'digitsBetween',
    ];

    /** @var list<string> */
    private const array STRING_ARG_RULES = [
        'same',
        'different',
        'after',
        'before',
        'afterOrEqual',
        'beforeOrEqual',
        'greaterThan',
        'lessThan',
        'greaterThanOrEqualTo',
        'lessThanOrEqualTo',
        'inArray',
        'dateEquals',
    ];

    /**
     * Methods that exist on FieldRule (the untyped fallback).
     * When type resolves to 'field', only these modifiers are safe to chain.
     * Methods not in this list (e.g., min, max, accepted, alpha) require a typed builder.
     *
     * @var list<string>
     */
    private const array FIELD_SAFE_MODIFIERS = [
        'required',
        'nullable',
        'sometimes',
        'filled',
        'present',
        'missing',
        'prohibited',
        'confirmed',
        'bail',
        'exclude',
        'same',
        'different',
    ];

    /** @var list<string> */
    private const array COMMA_SEPARATED_ARGS_RULES = [
        'requiredIf',
        'requiredUnless',
        'requiredWith',
        'requiredWithAll',
        'requiredWithout',
        'requiredWithoutAll',
        'requiredIfAccepted',
        'requiredIfDeclined',
        'excludeIf',
        'excludeUnless',
        'excludeWith',
        'excludeWithout',
        'prohibitedIf',
        'prohibitedUnless',
        'prohibitedIfAccepted',
        'prohibitedIfDeclined',
        'prohibits',
        'missingIf',
        'missingUnless',
        'missingWith',
        'missingWithAll',
        'presentIf',
        'presentUnless',
        'presentWith',
        'presentWithAll',
    ];

    /**
     * Process any class with a rules() method — FormRequest, Livewire component,
     * or any subclass through intermediate base classes.
     *
     * Instead of checking the parent class name (which misses intermediate classes
     * like SessionRequest → PlayerAjaxRequest → FormRequest), we detect any class
     * that has a rules() method with array return type.
     */
    private function refactorFormRequest(ClassLike $classLike): ?ClassLike
    {
        if (! $classLike instanceof Class_) {
            return null;
        }

        // Pre-scan ALL classes in the current file for parent::rules() manipulation.
        // This ensures we detect unsafe parents even when the child appears after the
        // parent in the same file. Cross-file cases are handled by the static set
        // persisting across file processing.
        $this->collectUnsafeParentClassesFromFile();

        // Abstract classes are designed to be extended. Subclasses may use
        // collect(parent::rules())->mergeRecursive(...) which breaks when the
        // parent returns FluentRule objects instead of plain arrays.
        if ($classLike->isAbstract()) {
            $this->logSkip($classLike, 'abstract class (subclasses may manipulate parent::rules() as plain arrays)');

            return null;
        }

        // Skip classes whose subclasses manipulate parent::rules() with array
        // functions (array_search, array_merge, in_array, bracket assignment).
        $className = $this->getName($classLike);

        if ($className !== null && $this->isUnsafeParentClass($className)) {
            $this->logSkip($classLike, 'unsafe parent: a subclass manipulates parent::rules() with array functions');

            return null;
        }

        $hasChanged = false;

        foreach ($classLike->getMethods() as $classMethod) {
            if (! $this->isName($classMethod, 'rules') && ! $this->hasFluentRulesAttribute($classMethod)) {
                continue;
            }

            $this->traverseNodesWithCallable($classMethod, function (Node $node) use (&$hasChanged): ?Return_ {
                if (! $node instanceof Return_ || ! $node->expr instanceof Array_) {
                    return null;
                }

                if ($this->processValidationRules($node->expr)) {
                    $hasChanged = true;

                    return $node;
                }

                return null;
            });
        }

        return $hasChanged ? $classLike : null;
    }

    /** @var string|null Track which file was last pre-scanned to avoid redundant work */
    private static ?string $lastScannedFile = null;

    /**
     * Check if a method is marked with the #[FluentRules] attribute.
     *
     * Opt-in detection for methods that hold validation rules under a name
     * other than `rules()` — e.g. `rulesWithoutPrefix()` on custom
     * FluentValidator subclasses used for JSON-import validation.
     */
    private function hasFluentRulesAttribute(ClassMethod $method): bool
    {
        // Referenced as a string (not ::class) so PHPStan doesn't require the
        // class to exist at static-analysis time. FluentRules ships in newer
        // laravel-fluent-validation releases but is absent from earlier
        // versions that still satisfy our ^1.0 constraint.
        $fluentRulesAttribute = FluentRules::class;

        foreach ($method->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                if ($this->getName($attr->name) === $fluentRulesAttribute) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Pre-scan all classes in the current file for parent::rules() manipulation.
     * Results are stored in the static $unsafeParentClasses set which persists
     * across files within a single Rector run.
     */
    private function collectUnsafeParentClassesFromFile(): void
    {
        $filePath = $this->getFile()->getFilePath();

        if (self::$lastScannedFile === $filePath) {
            return;
        }

        self::$lastScannedFile = $filePath;

        // Collect all top-level statements, unwrapping Rector's FileNode and Namespace_ wrappers
        $classNodes = [];

        foreach ($this->getFile()->getNewStmts() as $stmt) {
            $innerStmts = $stmt->stmts ?? [$stmt];

            foreach ($innerStmts as $innerStmt) {
                // Unwrap Namespace_ to get its class statements
                if ($innerStmt instanceof Namespace_) {
                    foreach ($innerStmt->stmts as $namespacedStmt) {
                        if ($namespacedStmt instanceof Class_ && $namespacedStmt->extends instanceof Name) {
                            $classNodes[] = $namespacedStmt;
                        }
                    }
                } elseif ($innerStmt instanceof Class_ && $innerStmt->extends instanceof Name) {
                    $classNodes[] = $innerStmt;
                }
            }
        }

        foreach ($classNodes as $classNode) {
            $this->collectUnsafeParentClass($classNode);
        }
    }

    /**
     * Detect if a single class calls parent::rules() and manipulates the result
     * with array functions or bracket assignment.
     */
    private function collectUnsafeParentClass(Class_ $class): void
    {
        $hasParentRulesCall = false;
        $hasArrayManipulation = false;

        foreach ($class->getMethods() as $method) {
            if (! $this->isName($method, 'rules')) {
                continue;
            }

            $this->traverseNodesWithCallable($method, function (Node $node) use (&$hasParentRulesCall, &$hasArrayManipulation): null {
                // Detect parent::rules()
                if ($node instanceof StaticCall
                    && $node->class instanceof Name
                    && $this->isName($node->class, 'parent')
                    && $this->isName($node->name, 'rules')) {
                    $hasParentRulesCall = true;
                }

                // Detect array manipulation functions
                if ($node instanceof FuncCall
                    && $node->name instanceof Name
                    && in_array($this->getName($node->name), [
                        'array_search', 'array_merge', 'array_merge_recursive',
                        'array_replace', 'array_splice', 'array_push', 'array_pop',
                        'array_shift', 'array_unshift', 'array_diff', 'array_intersect',
                        'array_filter', 'array_map', 'array_keys', 'array_values',
                        'array_combine', 'array_flip', 'array_reverse', 'array_slice',
                        'array_unique', 'array_walk', 'in_array', 'unset', 'collect',
                    ], true)) {
                    $hasArrayManipulation = true;
                }

                // Detect bracket assignment: $rules['key'] = ...
                if ($node instanceof Assign
                    && $node->var instanceof ArrayDimFetch) {
                    $hasArrayManipulation = true;
                }

                return null;
            });

            break;
        }

        if (! $hasParentRulesCall || ! $hasArrayManipulation) {
            return;
        }

        /** @var Name $extends */
        $extends = $class->extends;
        $parentName = $extends instanceof FullyQualified
            ? $extends->toString()
            : $this->getName($extends);

        if ($parentName !== null) {
            self::$unsafeParentClasses[$parentName] = true;
            $this->persistUnsafeParent($parentName);
        }
    }

    /**
     * Array manipulation functions that indicate a subclass treats parent::rules()
     * return values as plain arrays (not FluentRule objects).
     */
    private const string ARRAY_MANIPULATION_PATTERN = '/\barray_(?:search|merge|merge_recursive|replace|splice|push|pop|shift|unshift|diff|intersect|filter|map|keys|values|combine|flip|reverse|slice|unique|walk)\s*\(|\bin_array\s*\(/';

    /** Whether the filesystem scan has been performed in this process */
    private static bool $filesystemScanDone = false;

    /**
     * Check if a class FQCN is marked as unsafe to convert, checking:
     * 1. In-memory cache (same-file + same-process detections)
     * 2. Shared temp file (cross-process parallel worker detections)
     * 3. Filesystem scan (final fallback for race conditions, runs once per process)
     */
    private function isUnsafeParentClass(string $className): bool
    {
        // Fast path: check in-memory cache first
        if (isset(self::$unsafeParentClasses[$className])) {
            return true;
        }

        // Check shared temp file (written by other parallel workers)
        $this->loadUnsafeParentsFromDisk();

        if (isset(self::$unsafeParentClasses[$className])) {
            return true;
        }

        // Final fallback: scan ALL project PHP files once per process to find
        // subclasses that manipulate parent::rules(). This handles the race
        // condition where the parent is processed before any worker has seen
        // the child. Runs once and populates the full unsafe set.
        if (! self::$filesystemScanDone) {
            self::$filesystemScanDone = true;
            $this->scanProjectForUnsafeParents();
        }

        return isset(self::$unsafeParentClasses[$className]);
    }

    /**
     * Scan all PHP files in the project for classes that call parent::rules() with
     * array manipulation, and mark their parent classes as unsafe. Runs once per
     * process as a fallback for parallel workers that might miss cross-file patterns.
     */
    private function scanProjectForUnsafeParents(): void
    {
        $projectRoot = $this->findProjectRoot();

        if ($projectRoot === null) {
            return;
        }

        try {
            $directoryIterator = new RecursiveDirectoryIterator($projectRoot, FilesystemIterator::SKIP_DOTS);

            // Skip vendor, node_modules, and hidden directories entirely (don't descend)
            $filtered = new RecursiveCallbackFilterIterator(
                $directoryIterator,
                static function (SplFileInfo $file, string $key, RecursiveDirectoryIterator $iterator): bool {
                    if ($file->isDir()) {
                        $name = $file->getFilename();

                        return $name !== 'vendor' && $name !== 'node_modules' && ! str_starts_with($name, '.');
                    }

                    return $file->getExtension() === 'php';
                },
            );

            $iterator = new RecursiveIteratorIterator($filtered, RecursiveIteratorIterator::LEAVES_ONLY);
        } catch (UnexpectedValueException) {
            return;
        }

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {

            $content = @file_get_contents($file->getPathname());
            if ($content === false) {
                continue;
            }

            if (! str_contains($content, 'parent::rules()')) {
                continue;
            }

            // Check for array manipulation or bracket assignment
            if (preg_match(self::ARRAY_MANIPULATION_PATTERN, $content) !== 1
                && preg_match('/\$\w+\s*\[.*\]\s*=/', $content) !== 1) {
                continue;
            }

            // Extract the parent class FQCN from the extends clause + use imports/namespace
            $parentFqcn = $this->resolveParentFqcnFromSource($content);

            if ($parentFqcn !== null) {
                self::$unsafeParentClasses[$parentFqcn] = true;
                $this->persistUnsafeParent($parentFqcn);
            }
        }
    }

    /**
     * Find the project root directory (containing composer.json).
     */
    private function findProjectRoot(): ?string
    {
        $dir = dirname($this->getFile()->getFilePath());

        while ($dir !== dirname($dir)) {
            if (file_exists($dir . '/composer.json')) {
                return $dir;
            }

            $dir = dirname($dir);
        }

        return null;
    }

    /**
     * Persist an unsafe parent FQCN to the shared temp file so other
     * parallel Rector workers can see it.
     */
    private function persistUnsafeParent(string $parentFqcn): void
    {
        $path = self::unsafeParentsCachePath();
        $fp = fopen($path, 'a');

        if ($fp === false) {
            return;
        }

        flock($fp, LOCK_EX);
        fwrite($fp, $parentFqcn . "\n");
        flock($fp, LOCK_UN);
        fclose($fp);
    }

    /**
     * Load unsafe parent FQCNs from the shared temp file into memory.
     * Re-reads on every call to pick up entries written by other workers.
     */
    private function loadUnsafeParentsFromDisk(): void
    {
        $path = self::unsafeParentsCachePath();

        if (! file_exists($path)) {
            return;
        }

        $content = file_get_contents($path);

        if ($content === false || $content === '') {
            return;
        }

        foreach (explode("\n", trim($content)) as $fqcn) {
            if ($fqcn !== '') {
                self::$unsafeParentClasses[$fqcn] = true;
            }
        }
    }

    /**
     * Path to the shared temp file for cross-process unsafe parent tracking.
     * Scoped to the current working directory to avoid collisions.
     */
    private static function unsafeParentsCachePath(): string
    {
        return sys_get_temp_dir() . '/rector-fluent-unsafe-parents-' . hash('xxh128', (string) getcwd()) . '.txt';
    }

    /**
     * Resolve the FQCN of the parent class from raw PHP source code.
     * Handles both fully-qualified extends (\App\Foo) and short names
     * resolved via use imports or same-namespace.
     */
    private function resolveParentFqcnFromSource(string $content): ?string
    {
        if (preg_match('/class\s+\w+\s+extends\s+([\w\\\\]+)/', $content, $extendsMatch) !== 1) {
            return null;
        }

        $parentRef = $extendsMatch[1];

        // Already fully qualified
        if (str_contains($parentRef, '\\')) {
            return ltrim($parentRef, '\\');
        }

        // Check use imports: use App\Http\Requests\FooRequest;
        if (preg_match('/use\s+([\w\\\\]+\\\\' . preg_quote($parentRef, '/') . ')\s*;/', $content, $useMatch) === 1) {
            return $useMatch[1];
        }

        // Same namespace: namespace App\Http\Requests; class Child extends Parent
        if (preg_match('/namespace\s+([\w\\\\]+)\s*;/', $content, $nsMatch) === 1) {
            return $nsMatch[1] . '\\' . $parentRef;
        }

        // Global namespace
        return $parentRef;
    }

    /**
     * Process $request->validate([...]) calls.
     */
    private function refactorValidateCall(MethodCall $methodCall): ?MethodCall
    {
        if (! $this->isObjectType($methodCall->var, new ObjectType(Request::class))) {
            return null;
        }

        if ($methodCall->args === [] || ! $methodCall->args[0] instanceof Arg) {
            return null;
        }

        $rulesArgument = $methodCall->args[0]->value;

        if (! $rulesArgument instanceof Array_) {
            return null;
        }

        return $this->processValidationRules($rulesArgument) ? $methodCall : null;
    }

    /**
     * Process Validator::make($data, [...]) or $factory->make($data, [...]) calls.
     */
    private function refactorValidatorMake(StaticCall|MethodCall $node): StaticCall|MethodCall|null
    {
        if ($node instanceof StaticCall
            && ! $this->isObjectType($node->class, new ObjectType(Validator::class))) {
            return null;
        }

        if ($node instanceof MethodCall
            && ! $this->isObjectType($node->var, new ObjectType(Factory::class))) {
            return null;
        }

        if (count($node->args) < 2 || ! $node->args[1] instanceof Arg) {
            return null;
        }

        $rulesArgument = $node->args[1]->value;

        if (! $rulesArgument instanceof Array_) {
            return null;
        }

        return $this->processValidationRules($rulesArgument) ? $node : null;
    }

    /**
     * Parse a rule string part into name and args.
     *
     * @return array{name: string, args: ?string}
     */
    private function parseRulePart(string $part): array
    {
        $colonPos = strpos($part, ':');

        if ($colonPos === false) {
            return ['name' => $part, 'args' => null];
        }

        return [
            'name' => substr($part, 0, $colonPos),
            'args' => substr($part, $colonPos + 1),
        ];
    }

    /**
     * Set by buildFluentRuleFactory() whenever the rector emits a
     * `FluentRule::…()` factory call. Consuming rectors read this after
     * refactor() finishes and insert the matching `use SanderMuller\
     * FluentValidation\FluentRule;` import when needed. Reset per-file by
     * the caller before dispatching into this trait's logic.
     */
    private bool $needsFluentRuleImport = false;

    /**
     * Build a FluentRule::type() static call as the chain root.
     *
     * Emits the short `FluentRule` name and marks the enclosing file as
     * needing a `use SanderMuller\FluentValidation\FluentRule;` import so
     * the short reference resolves at runtime. The import itself is added
     * by the consuming rector via ManagesNamespaceImports.
     *
     * @param  list<Arg>  $args
     *
     * @phpstan-impure
     */
    private function buildFluentRuleFactory(string $type, array $args = []): StaticCall
    {
        $this->needsFluentRuleImport = true;

        return new StaticCall(
            new Name('FluentRule'),
            new Identifier($type),
            $args,
        );
    }

    private function buildModifierCall(Expr $expr, string $name, ?string $args): ?MethodCall
    {
        if (in_array($name, self::SIMPLE_MODIFIERS, true)) {
            return new MethodCall($expr, new Identifier($name));
        }

        if (in_array($name, self::NUMERIC_ARG_RULES, true) && $args !== null) {
            return new MethodCall($expr, new Identifier($name), [
                new Arg($this->parseNumericArg($args)),
            ]);
        }

        if (in_array($name, self::TWO_NUMERIC_ARG_RULES, true) && $args !== null) {
            $argParts = explode(',', $args, 2);

            if (count($argParts) !== 2) {
                return null;
            }

            return new MethodCall($expr, new Identifier($name), [
                new Arg($this->parseNumericArg($argParts[0])),
                new Arg($this->parseNumericArg($argParts[1])),
            ]);
        }

        if (in_array($name, self::STRING_ARG_RULES, true) && $args !== null) {
            return new MethodCall($expr, new Identifier($name), [
                new Arg(new String_($args)),
            ]);
        }

        if ($name === 'in' && $args !== null) {
            return $this->buildArrayArgCall($expr, 'in', $args);
        }

        if ($name === 'notIn' && $args !== null) {
            return $this->buildArrayArgCall($expr, 'notIn', $args);
        }

        if (($name === 'regex' || $name === 'notRegex') && $args !== null) {
            return new MethodCall($expr, new Identifier($name), [
                new Arg(new String_($args)),
            ]);
        }

        if ($name === 'exists' && $args !== null) {
            return $this->buildTableColumnCall($expr, 'exists', $args);
        }

        if ($name === 'unique' && $args !== null) {
            return $this->buildTableColumnCall($expr, 'unique', $args);
        }

        if ((in_array($name, ['startsWith', 'endsWith', 'doesntStartWith', 'doesntEndWith'], true)) && $args !== null) {
            return new MethodCall($expr, new Identifier($name), [
                new Arg(new String_($args)),
            ]);
        }

        if ($name === 'format' && $args !== null) {
            return new MethodCall($expr, new Identifier('format'), [
                new Arg(new String_($args)),
            ]);
        }

        if (in_array($name, self::COMMA_SEPARATED_ARGS_RULES, true) && $args !== null) {
            return new MethodCall($expr, new Identifier($name), array_map(
                static fn (string $v): Arg => new Arg(new String_($v)),
                explode(',', $args),
            ));
        }

        return null;
    }

    /**
     * Tuple-form analogue of buildModifierCall().
     *
     * Lowers array-form rule tuples like ['max', 65535] and ['between', 3, 100]
     * directly to fluent method calls (->max(65535), ->between(3, 100)) using
     * the tuple's already-parsed Expr arguments, instead of routing through
     * string reparsing. Returns null when the tuple's rule name or arity doesn't
     * match a known fluent method — caller is expected to fall back to the
     * ->rule(['name', ...]) escape hatch.
     *
     * @param  list<Expr>  $argExprs  tuple arguments in source order, without the rule name
     */
    private function buildModifierCallFromTupleExprArgs(Expr $expr, string $name, array $argExprs): ?MethodCall
    {
        $argCount = count($argExprs);

        if (in_array($name, self::SIMPLE_MODIFIERS, true)) {
            return $argCount === 0
                ? new MethodCall($expr, new Identifier($name))
                : null;
        }

        if (in_array($name, self::NUMERIC_ARG_RULES, true)) {
            return $argCount === 1
                ? new MethodCall($expr, new Identifier($name), [new Arg($argExprs[0])])
                : null;
        }

        if (in_array($name, self::TWO_NUMERIC_ARG_RULES, true)) {
            return $argCount === 2
                ? new MethodCall($expr, new Identifier($name), [
                    new Arg($argExprs[0]),
                    new Arg($argExprs[1]),
                ])
                : null;
        }

        if (in_array($name, self::STRING_ARG_RULES, true)) {
            return $argCount === 1
                ? new MethodCall($expr, new Identifier($name), [new Arg($argExprs[0])])
                : null;
        }

        if (in_array($name, ['startsWith', 'endsWith', 'doesntStartWith', 'doesntEndWith', 'regex', 'notRegex', 'format'], true)) {
            return $argCount === 1
                ? new MethodCall($expr, new Identifier($name), [new Arg($argExprs[0])])
                : null;
        }

        return null;
    }

    /**
     * Build ->in(['val1', 'val2']) or ->notIn(['val1', 'val2'])
     */
    private function buildArrayArgCall(Expr $expr, string $method, string $args): MethodCall
    {
        $values = array_map(
            static fn (string $v): ArrayItem => new ArrayItem(new String_($v)),
            explode(',', $args),
        );

        return new MethodCall($expr, new Identifier($method), [
            new Arg(new Array_($values)),
        ]);
    }

    /**
     * Build ->exists('table', 'column') or ->unique('table', 'column')
     * Bail (return null) on 3+ args (extra where clauses, ignore ID, etc.)
     */
    private function buildTableColumnCall(Expr $expr, string $method, string $args): ?MethodCall
    {
        $argParts = explode(',', $args);

        if (count($argParts) > 2) {
            return null;
        }

        $callArgs = [new Arg(new String_($argParts[0]))];

        if (isset($argParts[1]) && $argParts[1] !== '') {
            $callArgs[] = new Arg(new String_($argParts[1]));
        }

        return new MethodCall($expr, new Identifier($method), $callArgs);
    }

    private function parseNumericArg(string $value): Int_|String_
    {
        if (ctype_digit($value) || (str_starts_with($value, '-') && ctype_digit(substr($value, 1)))) {
            return new Int_((int) $value);
        }

        return new String_($value);
    }

    private function normalizeRuleName(string $name): string
    {
        return match ($name) {
            'not_in' => 'notIn',
            'not_regex' => 'notRegex',
            'starts_with' => 'startsWith',
            'ends_with' => 'endsWith',
            'doesnt_start_with' => 'doesntStartWith',
            'doesnt_end_with' => 'doesntEndWith',
            'digits_between' => 'digitsBetween',
            'min_digits' => 'minDigits',
            'max_digits' => 'maxDigits',
            'multiple_of' => 'multipleOf',
            'greater_than' => 'greaterThan',
            'less_than' => 'lessThan',
            'greater_than_or_equal_to' => 'greaterThanOrEqualTo',
            'less_than_or_equal_to' => 'lessThanOrEqualTo',
            'alpha_dash' => 'alphaDash',
            'alpha_num' => 'alphaNumeric',
            'in_array' => 'inArray',
            'required_if' => 'requiredIf',
            'required_unless' => 'requiredUnless',
            'required_with' => 'requiredWith',
            'required_with_all' => 'requiredWithAll',
            'required_without' => 'requiredWithout',
            'required_without_all' => 'requiredWithoutAll',
            'exclude_if' => 'excludeIf',
            'exclude_unless' => 'excludeUnless',
            'exclude_with' => 'excludeWith',
            'exclude_without' => 'excludeWithout',
            'prohibited_if' => 'prohibitedIf',
            'prohibited_unless' => 'prohibitedUnless',
            'date_equals' => 'dateEquals',
            'date_format' => 'format',
            'after_or_equal' => 'afterOrEqual',
            'before_or_equal' => 'beforeOrEqual',
            'mac_address' => 'macAddress',
            'hex_color' => 'hexColor',
            'current_password' => 'currentPassword',
            'active_url' => 'activeUrl',
            'present_if' => 'presentIf',
            'present_unless' => 'presentUnless',
            'present_with' => 'presentWith',
            'present_with_all' => 'presentWithAll',
            'missing_if' => 'missingIf',
            'missing_unless' => 'missingUnless',
            'missing_with' => 'missingWith',
            'missing_with_all' => 'missingWithAll',
            'required_if_accepted' => 'requiredIfAccepted',
            'required_if_declined' => 'requiredIfDeclined',
            'prohibited_if_accepted' => 'prohibitedIfAccepted',
            'prohibited_if_declined' => 'prohibitedIfDeclined',
            default => $name,
        };
    }

    /**
     * Check if a modifier name is valid for the given FluentRule type.
     * When type is 'field' (untyped fallback), only a subset of modifiers
     * are available. Type-specific methods like min(), max(), accepted(),
     * alpha() etc. require a typed builder.
     */
    private function isModifierValidForType(string $type, string $modifierName): bool
    {
        if ($type !== 'field') {
            return true;
        }

        // For field type, only presence modifiers and embedded rules are safe
        return in_array($modifierName, self::FIELD_SAFE_MODIFIERS, true)
            || in_array($modifierName, self::COMMA_SEPARATED_ARGS_RULES, true);
    }

    /**
     * Implemented by each rule to process the specific value format (String_ vs Array_).
     */
    abstract private function processValidationRules(Array_ $array): bool;
}

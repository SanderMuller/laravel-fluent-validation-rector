<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Internal;

use FilesystemIterator;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use UnexpectedValueException;

/**
 * Records which classes extend which, and which method names each class
 * calls on itself or on its parent (`parent::name()`, `$this->name()`,
 * `self::name()`, `static::name()`). From that it answers whether a
 * descendant of a class calls a given method.
 *
 * The grouping rector uses it to skip a fold in a method whose result a
 * subclass combines with its own keys. The signal sits in the subclass file
 * and the decision applies to the parent file, so the project is scanned
 * once per process. The scan reads each file from disk and uses regular
 * expressions. It does not depend on the order in which Rector visits files.
 *
 * Classes are keyed by lower-cased FQCN without a leading backslash.
 *
 * @internal
 */
final class DescendantMethodCalls
{
    /** @var array<string, array<string, true>> parent FQCN → child FQCNs */
    private static array $children = [];

    /** @var array<string, array<string, true>> class FQCN → called method names */
    private static array $calledMethods = [];

    /** @var array<string, true> project roots already scanned */
    private static array $scannedRoots = [];

    private const string CALL_PATTERN = '/(?:\bparent::|\$this\s*->\s*|\b(?:self|static)::)(\w+)\s*\(/i';

    /**
     * @param  list<string>  $calledMethodNames
     */
    public static function record(string $classFqcn, string $parentFqcn, array $calledMethodNames): void
    {
        $class = self::key($classFqcn);
        self::$children[self::key($parentFqcn)][$class] = true;

        foreach ($calledMethodNames as $calledMethodName) {
            self::$calledMethods[$class][strtolower($calledMethodName)] = true;
        }
    }

    /**
     * True when a direct or indirect subclass of `$classFqcn` calls a
     * method named `$methodName`.
     */
    public static function isCalledByDescendant(string $classFqcn, string $methodName, string $projectRoot): bool
    {
        self::scanProject($projectRoot);

        $method = strtolower($methodName);
        $queue = array_keys(self::$children[self::key($classFqcn)] ?? []);
        $seen = [];

        while ($queue !== []) {
            $class = array_pop($queue);

            if (isset($seen[$class])) {
                continue;
            }

            $seen[$class] = true;

            if (isset(self::$calledMethods[$class][$method])) {
                return true;
            }

            foreach (array_keys(self::$children[$class] ?? []) as $child) {
                $queue[] = $child;
            }
        }

        return false;
    }

    /**
     * The nearest directory at or above `$filePath` that holds a
     * composer.json, or null.
     */
    public static function findProjectRoot(string $filePath): ?string
    {
        $dir = dirname($filePath);

        while ($dir !== dirname($dir)) {
            if (is_file($dir . '/composer.json')) {
                return $dir;
            }

            $dir = dirname($dir);
        }

        return null;
    }

    public static function reset(): void
    {
        self::$children = [];
        self::$calledMethods = [];
        self::$scannedRoots = [];
    }

    private static function scanProject(string $projectRoot): void
    {
        if (isset(self::$scannedRoots[$projectRoot])) {
            return;
        }

        self::$scannedRoots[$projectRoot] = true;

        try {
            $filtered = new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator($projectRoot, FilesystemIterator::SKIP_DOTS),
                static function (SplFileInfo $file): bool {
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

            if ($content === false || stripos($content, 'extends') === false) {
                continue;
            }

            self::recordSource($content);
        }
    }

    /**
     * Records every `class X extends Y` in the source. A file with several
     * classes gives each of them every call in the file, which can only
     * cause an extra skip.
     */
    public static function recordSource(string $content): void
    {
        if (preg_match_all('/\bclass\s+(\w+)\s+extends\s+(\\\\?[\w\\\\]+)/i', $content, $classMatches, PREG_SET_ORDER) === 0) {
            return;
        }

        preg_match_all(self::CALL_PATTERN, $content, $callMatches);
        $calledMethodNames = $callMatches[1];

        $namespace = preg_match('/^\s*namespace\s+([\w\\\\]+)\s*;/m', $content, $namespaceMatch) === 1
            ? $namespaceMatch[1]
            : '';
        $imports = self::parseImports($content);

        foreach ($classMatches as [, $className, $parentName]) {
            self::record(
                ltrim($namespace . '\\' . $className, '\\'),
                self::resolveName($parentName, $namespace, $imports),
                $calledMethodNames,
            );
        }
    }

    /**
     * @return array<string, string> lower-cased alias → FQCN
     */
    private static function parseImports(string $content): array
    {
        $imports = [];

        preg_match_all('/^\s*use\s+\\\\?([\w\\\\]+)(?:\s+as\s+(\w+))?\s*;/mi', $content, $useMatches, PREG_SET_ORDER);

        foreach ($useMatches as $useMatch) {
            $fqcn = $useMatch[1];
            $alias = isset($useMatch[2]) && $useMatch[2] !== ''
                ? $useMatch[2]
                : substr($fqcn, (int) strrpos('\\' . $fqcn, '\\'));
            $imports[strtolower($alias)] = $fqcn;
        }

        return $imports;
    }

    /**
     * @param  array<string, string>  $imports
     */
    private static function resolveName(string $name, string $namespace, array $imports): string
    {
        if (str_starts_with($name, '\\')) {
            return ltrim($name, '\\');
        }

        $parts = explode('\\', $name, 2);
        $first = strtolower($parts[0]);

        if (isset($imports[$first])) {
            return $imports[$first] . (isset($parts[1]) ? '\\' . $parts[1] : '');
        }

        return ltrim($namespace . '\\' . $name, '\\');
    }

    private static function key(string $fqcn): string
    {
        return strtolower(ltrim($fqcn, '\\'));
    }
}

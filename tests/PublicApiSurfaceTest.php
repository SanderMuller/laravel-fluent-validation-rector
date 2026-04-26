<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionClassConstant;
use SanderMuller\FluentValidationRector\Rector\AddHasFluentRulesTraitRector;
use SanderMuller\FluentValidationRector\Rector\ConvertLivewireRuleAttributeRector;
use SanderMuller\FluentValidationRector\Rector\SimplifyRuleWrappersRector;
use SanderMuller\FluentValidationRector\Rector\UpdateRulesReturnTypeDocblockRector;
use SanderMuller\FluentValidationRector\Set\FluentValidationSetList;

/**
 * Drift checks between the source and PUBLIC_API.md. Three independent audits:
 *
 * 1. Every `public const` on a public class is documented.
 * 2. Every literal `$configuration[<key>]` access in a configurable rector is
 *    a documented wire key.
 * 3. Every documented wire key matches the runtime value of the constant
 *    whose symbol the docs pair it with.
 */
final class PublicApiSurfaceTest extends TestCase
{
    private const string PUBLIC_API_PATH = __DIR__ . '/../PUBLIC_API.md';

    /**
     * @var list<class-string>
     */
    private const array CONSTANT_HOST_CLASSES = [
        FluentValidationSetList::class,
        AddHasFluentRulesTraitRector::class,
        ConvertLivewireRuleAttributeRector::class,
        SimplifyRuleWrappersRector::class,
        UpdateRulesReturnTypeDocblockRector::class,
    ];

    /**
     * @var list<class-string>
     */
    private const array CONFIGURABLE_RECTORS = [
        AddHasFluentRulesTraitRector::class,
        ConvertLivewireRuleAttributeRector::class,
        SimplifyRuleWrappersRector::class,
        UpdateRulesReturnTypeDocblockRector::class,
    ];

    public function testEveryPublicConstantIsDocumented(): void
    {
        $documented = $this->parseDocumentedConstants();
        $missing = [];

        foreach (self::CONSTANT_HOST_CLASSES as $fqn) {
            $reflection = new ReflectionClass($fqn);
            $shortName = $reflection->getShortName();
            $documentedForClass = $documented[$shortName] ?? [];

            foreach ($reflection->getReflectionConstants() as $constant) {
                if (! $constant->isPublic()) {
                    continue;
                }

                if ($constant->getDeclaringClass()->getName() !== $fqn) {
                    continue;
                }

                $docComment = $constant->getDocComment();

                if (is_string($docComment) && preg_match('/^\s*\*\s*@internal\b/m', $docComment)) {
                    continue;
                }

                if (! in_array($constant->getName(), $documentedForClass, true)) {
                    $missing[] = "{$fqn}::{$constant->getName()}";
                }
            }
        }

        $this->assertSame(
            [],
            $missing,
            'Every public constant on a documented class must appear in '
            . 'PUBLIC_API.md. Add the missing entries (or mark the constant '
            . "private if it should not be public).\n\nMissing:\n  - "
            . implode("\n  - ", $missing),
        );
    }

    public function testEveryConfigurationKeyAccessUsesDocumentedWireKey(): void
    {
        $documentedWireKeys = $this->parseDocumentedWireKeys();
        $offenders = [];

        foreach (self::CONFIGURABLE_RECTORS as $fqn) {
            $reflection = new ReflectionClass($fqn);
            $shortName = $reflection->getShortName();
            $allowed = $documentedWireKeys[$shortName] ?? [];

            $usedKeys = $this->collectConfigurationLiteralKeys($fqn);

            foreach ($usedKeys as $literal) {
                if (! in_array($literal, $allowed, true)) {
                    $offenders[] = "{$fqn}: \$configuration['{$literal}'] is not a documented wire key";
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'Every literal-string key passed to $configuration[...] in a '
            . "configurable rector must appear under that rector's wire-key "
            . "section in PUBLIC_API.md.\n\nOffenders:\n  - "
            . implode("\n  - ", $offenders),
        );
    }

    public function testDocumentedWireKeysMatchConstantValues(): void
    {
        $constantsWithValues = $this->parseDocumentedConstantValues();
        $mismatches = [];

        foreach ($constantsWithValues as $shortName => $entries) {
            $fqn = $this->resolveShortName($shortName);

            if ($fqn === null) {
                continue;
            }

            foreach ($entries as $constName => $documentedValue) {
                if ($documentedValue === null) {
                    continue;
                }

                if (! defined("{$fqn}::{$constName}")) {
                    $mismatches[] = "{$fqn}::{$constName}: documented but not defined";

                    continue;
                }

                $constant = new ReflectionClassConstant($fqn, $constName);
                $runtimeValue = $constant->getValue();

                if ($runtimeValue !== $documentedValue) {
                    $rendered = is_scalar($runtimeValue) ? var_export($runtimeValue, true) : gettype($runtimeValue);
                    $mismatches[] = "{$fqn}::{$constName}: documented '{$documentedValue}', runtime {$rendered}";
                }
            }
        }

        $this->assertSame(
            [],
            $mismatches,
            'Each constant whose documented value is a wire-key string must '
            . 'have a runtime value matching the documentation (lockstep '
            . "rename guard).\n\nMismatches:\n  - "
            . implode("\n  - ", $mismatches),
        );
    }

    /**
     * @return array<string, list<string>>
     */
    private function parseDocumentedConstants(): array
    {
        $markdown = $this->loadPublicApi();
        $result = [];

        // SetList lives under "## Set list constants" with `ClassName::CONST` form.
        $setListSection = $this->extractSection($markdown, '## Set list constants');

        if ($setListSection !== null) {
            $constants = [];
            preg_match_all('/^- `[A-Za-z][A-Za-z0-9]*::([A-Z][A-Z0-9_]*)`/m', $setListSection, $matches);

            $constants = $matches[1];

            if ($constants !== []) {
                $result['FluentValidationSetList'] = array_unique($constants);
            }
        }

        // Per-rector subsections live under "### {ClassName}" within "## Rector configuration constants".
        $sections = $this->splitOnHeading($markdown, '###');

        foreach ($sections as $heading => $body) {
            if (str_contains($heading, 'wire keys')) {
                continue;
            }

            $shortName = $this->extractClassShortName($heading);

            if ($shortName === null) {
                continue;
            }

            $constants = [];
            preg_match_all('/^- `([A-Z][A-Z0-9_]*)`/m', $body, $matches);

            $constants = $matches[1];

            if ($constants !== []) {
                $result[$shortName] = array_unique($constants);
            }
        }

        return $result;
    }

    private function extractSection(string $markdown, string $heading): ?string
    {
        $offset = strpos($markdown, $heading . "\n");

        if ($offset === false) {
            return null;
        }

        $start = $offset + strlen($heading) + 1;
        $tail = substr($markdown, $start);

        $next = preg_match('/^##\s/m', $tail, $match, PREG_OFFSET_CAPTURE);

        if ($next === 1) {
            return substr($tail, 0, $match[0][1]);
        }

        return $tail;
    }

    /**
     * @return array<string, array<string, string|null>>
     */
    private function parseDocumentedConstantValues(): array
    {
        $markdown = $this->loadPublicApi();
        $sections = $this->splitOnHeading($markdown, '###');
        $result = [];

        foreach ($sections as $heading => $body) {
            if (str_contains($heading, 'wire keys')) {
                continue;
            }

            $shortName = $this->extractClassShortName($heading);

            if ($shortName === null) {
                continue;
            }

            $entries = [];
            preg_match_all(
                '/^- `([A-Z][A-Z0-9_]*)`(?:\s*\(value:\s*`\'([^\']+)\'`\))?/m',
                $body,
                $matches,
                PREG_SET_ORDER,
            );

            foreach ($matches as $match) {
                $constName = $match[1];
                $value = isset($match[2]) && $match[2] !== '' ? $match[2] : null;
                $entries[$constName] = $value;
            }

            if ($entries !== []) {
                $result[$shortName] = $entries;
            }
        }

        return $result;
    }

    /**
     * @return array<string, list<string>>
     */
    private function parseDocumentedWireKeys(): array
    {
        $markdown = $this->loadPublicApi();
        $sections = $this->splitOnHeading($markdown, '###');
        $result = [];

        foreach ($sections as $heading => $body) {
            if (! str_contains($heading, 'wire keys')) {
                continue;
            }

            $shortName = $this->extractClassShortName($heading);

            if ($shortName === null) {
                continue;
            }

            $keys = [];
            preg_match_all("/^- `'([^']+)'`/m", $body, $matches);

            $keys = $matches[1];

            if ($keys !== []) {
                $result[$shortName] = array_unique($keys);
            }
        }

        return $result;
    }

    /**
     * @param  class-string  $fqn
     * @return list<string>
     */
    private function collectConfigurationLiteralKeys(string $fqn): array
    {
        $reflection = new ReflectionClass($fqn);
        $traitFiles = array_map(
            static fn (ReflectionClass $trait): string|false => $trait->getFileName(),
            $reflection->getTraits(),
        );

        $files = array_filter([
            $reflection->getFileName(),
            ...$traitFiles,
        ]);

        $parser = (new ParserFactory())->createForHostVersion();
        $literals = [];

        foreach ($files as $file) {
            $source = file_get_contents($file);

            if ($source === false) {
                continue;
            }

            $ast = $parser->parse($source);

            if ($ast === null) {
                continue;
            }

            $visitor = new ConfigurationKeyCollector($fqn);
            $traverser = new NodeTraverser();
            $traverser->addVisitor($visitor);
            $traverser->traverse($ast);

            foreach ($visitor->literals as $literal) {
                $literals[] = $literal;
            }

            foreach ($visitor->constReferences as $constName) {
                if (defined("{$fqn}::{$constName}")) {
                    $value = (new ReflectionClassConstant($fqn, $constName))->getValue();

                    if (is_string($value)) {
                        $literals[] = $value;
                    }
                }
            }
        }

        return array_values(array_unique($literals));
    }

    /**
     * @return array<string, string>
     */
    private function splitOnHeading(string $markdown, string $headingPrefix): array
    {
        $lines = explode("\n", $markdown);
        $sections = [];
        $current = null;
        $buffer = [];
        $prefix = $headingPrefix . ' ';

        foreach ($lines as $line) {
            if (str_starts_with($line, $prefix)) {
                if ($current !== null) {
                    $sections[$current] = implode("\n", $buffer);
                }

                $current = substr($line, strlen($prefix));
                $buffer = [];

                continue;
            }

            if ($current !== null) {
                $buffer[] = $line;
            }
        }

        if ($current !== null) {
            $sections[$current] = implode("\n", $buffer);
        }

        return $sections;
    }

    private function extractClassShortName(string $heading): ?string
    {
        if (preg_match('/`([A-Z][A-Za-z0-9]+)`/', $heading, $match)) {
            return $match[1];
        }

        return null;
    }

    private function resolveShortName(string $shortName): ?string
    {
        foreach (self::CONSTANT_HOST_CLASSES as $fqn) {
            if ((new ReflectionClass($fqn))->getShortName() === $shortName) {
                return $fqn;
            }
        }

        return null;
    }

    private function loadPublicApi(): string
    {
        $contents = file_get_contents(self::PUBLIC_API_PATH);
        $this->assertNotFalse($contents, 'PUBLIC_API.md must be readable.');

        return $contents;
    }
}

/**
 * @internal
 */
final class ConfigurationKeyCollector extends NodeVisitorAbstract
{
    /**
     * @var list<string>
     */
    public array $literals = [];

    /**
     * @var list<string>
     */
    public array $constReferences = [];

    public function __construct(private readonly string $rectorFqn) {}

    public function enterNode(Node $node): null
    {
        if (! $node instanceof ArrayDimFetch) {
            return null;
        }

        if (! $node->var instanceof Variable || $node->var->name !== 'configuration') {
            return null;
        }

        $dim = $node->dim;

        if ($dim instanceof String_) {
            $this->literals[] = $dim->value;

            return null;
        }

        if ($dim instanceof ClassConstFetch
            && $dim->class instanceof Name
            && $dim->name instanceof Identifier
        ) {
            $className = $dim->class->toString();

            if (in_array($className, ['self', 'static', $this->rectorFqn], true)) {
                $this->constReferences[] = $dim->name->toString();
            }
        }

        return null;
    }
}

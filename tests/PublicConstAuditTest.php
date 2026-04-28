<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionClassConstant;
use ReflectionException;
use SanderMuller\FluentValidationRector\Rector\AddHasFluentRulesTraitRector;
use SanderMuller\FluentValidationRector\Rector\ConvertLivewireRuleAttributeRector;
use SanderMuller\FluentValidationRector\Rector\SimplifyRuleWrappersRector;
use SanderMuller\FluentValidationRector\Rector\UpdateRulesReturnTypeDocblockRector;
use SplFileInfo;

/**
 * Audits every `public const` on a non-`@internal` class under `src/Rector/`
 * (excluding `Internal/` and `Concerns/`) against the package's two-tier
 * commitment rule:
 *
 *   1. Documented in `PUBLIC_API.md` ("Rector configuration constants" or
 *      "Wire keys" section) — the constant is part of the SemVer commitment
 *      surface and consumers may import it.
 *   2. Carries a `@internal` PHPDoc tag on the constant declaration —
 *      mechanical enforcement of "anything not listed is implementation".
 *
 * Without this audit, a contributor adding a new `public const` to a public
 * rector class without a `@internal` tag silently extends the API surface;
 * downstream tools (PHPStan, IDEs) can't flag external usage as
 * unsupported, and the doc-only "anything not listed is internal" rule
 * works *legally* per SemVer but isn't *mechanically enforceable*.
 *
 * Catches future drift across the public-rector × public-const cross-product
 * — the surface that grows as new rectors land. Pairs with
 * `InternalAuditTest` (class-level @internal coverage) and
 * `PublicApiSurfaceTest` (constant-level + wire-key audits at the
 * doc-resolution boundary).
 */
final class PublicConstAuditTest extends TestCase
{
    /**
     * Constants listed in `PUBLIC_API.md` — every entry below has a
     * corresponding line in the doc's "Rector configuration constants"
     * section. Adding a new public-API constant requires updating both this
     * list AND the doc; the audit-evidence check in this test is the
     * *mechanical* half (enforces the implication "if the constant isn't
     * here AND isn't @internal, it's a leak").
     *
     * @var array<string, list<string>>
     */
    private const array DOCUMENTED_PUBLIC_CONSTS = [
        AddHasFluentRulesTraitRector::class => [
            'BASE_CLASSES',
        ],
        ConvertLivewireRuleAttributeRector::class => [
            'PRESERVE_REALTIME_VALIDATION',
            'MIGRATE_MESSAGES',
            'KEY_OVERLAP_BEHAVIOR',
            'OVERLAP_BEHAVIOR_BAIL',
            'OVERLAP_BEHAVIOR_PARTIAL',
        ],
        SimplifyRuleWrappersRector::class => [
            'TREAT_AS_FLUENT_COMPATIBLE',
            'ALLOW_CHAIN_TAIL_ON_ALLOWLISTED',
        ],
        UpdateRulesReturnTypeDocblockRector::class => [
            'TREAT_AS_FLUENT_COMPATIBLE',
            'ALLOW_CHAIN_TAIL_ON_ALLOWLISTED',
        ],
    ];

    public function testEveryPublicConstIsEitherDocumentedOrInternal(): void
    {
        $offenders = [];

        foreach ($this->discoverPublicRectorSymbols() as $fqn) {
            try {
                $reflection = new ReflectionClass($fqn);
            } catch (ReflectionException $e) {
                $offenders[] = "{$fqn}: ReflectionException ({$e->getMessage()})";

                continue;
            }

            // Skip classes that are themselves @internal — their public
            // constants inherit the marker.
            $classDoc = $reflection->getDocComment();
            if (is_string($classDoc) && preg_match('/^\s*\*\s*@internal\b/m', $classDoc) === 1) {
                continue;
            }

            foreach ($reflection->getReflectionConstants() as $const) {
                if (! $const->isPublic()) {
                    continue;
                }

                if ($const->getDeclaringClass()->getName() !== $fqn) {
                    // Inherited constant — audited on the declaring class.
                    continue;
                }

                $name = $const->getName();
                $documented = in_array($name, self::DOCUMENTED_PUBLIC_CONSTS[$fqn] ?? [], true);
                $internalTagged = $this->constHasInternalTag($const);

                if (! $documented && ! $internalTagged) {
                    $offenders[] = "{$fqn}::{$name} — public, not in PUBLIC_API.md DOCUMENTED_PUBLIC_CONSTS, no @internal PHPDoc tag";
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'Every `public const` on a non-@internal rector class must be either '
            . "listed in PUBLIC_API.md (and reflected in this test's "
            . 'DOCUMENTED_PUBLIC_CONSTS map) OR carry a `@internal` PHPDoc tag '
            . 'on the constant declaration. The doc-only rule "anything not '
            . 'listed is internal" works legally per SemVer but isn\'t '
            . "mechanically enforceable without the tag.\n\nOffenders:\n  - "
            . implode("\n  - ", $offenders),
        );
    }

    public function testDocumentedPublicConstsActuallyResolve(): void
    {
        $offenders = [];

        foreach (self::DOCUMENTED_PUBLIC_CONSTS as $fqn => $names) {
            if (! class_exists($fqn)) {
                $offenders[] = "{$fqn}: class does not exist";

                continue;
            }

            $reflection = new ReflectionClass($fqn);
            foreach ($names as $name) {
                if (! $reflection->hasConstant($name)) {
                    $offenders[] = "{$fqn}::{$name}: declared in PUBLIC_API.md but not on the class";
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'Every entry in DOCUMENTED_PUBLIC_CONSTS must resolve against the '
            . 'current `src/`. A drift here means PUBLIC_API.md claims a '
            . 'symbol that doesn\'t exist — consumers pinned to the doc would '
            . "fatal on bump.\n\nOffenders:\n  - "
            . implode("\n  - ", $offenders),
        );
    }

    /**
     * @return list<class-string>
     */
    private function discoverPublicRectorSymbols(): array
    {
        $srcRoot = dirname(__DIR__) . '/src/Rector';
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($srcRoot, FilesystemIterator::SKIP_DOTS),
        );

        $fqns = [];

        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo) {
                continue;
            }

            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = substr($file->getPathname(), strlen($srcRoot) + 1);
            $relative = str_replace(\DIRECTORY_SEPARATOR, '\\', $relative);

            // Skip Rector\Concerns\ — traits, always @internal by convention.
            if (str_starts_with($relative, 'Concerns\\')) {
                continue;
            }

            $fqn = 'SanderMuller\\FluentValidationRector\\Rector\\'
                . preg_replace('/\.php$/', '', $relative);
            if (! is_string($fqn)) {
                continue;
            }

            if ($fqn === '') {
                continue;
            }

            if (! class_exists($fqn)) {
                continue;
            }

            $fqns[] = $fqn;
        }

        sort($fqns);

        /** @var list<class-string> $fqns */
        return $fqns;
    }

    private function constHasInternalTag(ReflectionClassConstant $const): bool
    {
        $doc = $const->getDocComment();

        if ($doc === false) {
            return false;
        }

        return preg_match('/^\s*\*\s*@internal\b/m', $doc) === 1;
    }
}

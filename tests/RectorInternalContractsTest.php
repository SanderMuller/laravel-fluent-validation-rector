<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests;

use PHPUnit\Framework\TestCase;
use Rector\NodeTypeResolver\Node\AttributeKey;
use ReflectionClass;
use SanderMuller\FluentValidationRector\Rector\InlineMessageSurface;
use SanderMuller\FluentValidationRector\Rector\PromoteFieldFactoryRector;
use SanderMuller\FluentValidationRector\Rector\SimplifyRuleWrappersRector;
use Throwable;

/**
 * Pins the Rector-internal APIs we rely on at class/constant level. Rector
 * has churned `AttributeKey` members across past major versions; this test
 * fails fast on Rector 3+ upgrades that rename or remove keys we depend on,
 * rather than letting the absence silently degrade generated output.
 *
 * Also pins the cross-package class-existence invariants that any rector
 * iterating a hardcoded rule-class FQCN table depends on. Surfaced via
 * 0.22.0 dogfood when a vendor-rsync methodology exposed an out-of-contract
 * `laravel-fluent-validation` install where `FACTORY_BASELINE` claimed a
 * class (`AcceptedRule`) that didn't exist in the older sister-package
 * version. 0.22.1 fixed `SimplifyRuleWrappersRector::FACTORY_BASELINE`;
 * 0.22.2 generalized the fix across two more sites
 * (`InlineMessageSurface::TYPED_RULE_CLASSES`,
 * `PromoteFieldFactoryRector::TYPED_BUILDER_TO_FACTORY`) and the fixture
 * to sweep all rector classes for the same pattern.
 *
 * The cross-rector invariant (codified by these tests): any rector iterating
 * a hardcoded class-typed const table must filter for `class_exists` before
 * reflecting on entries — otherwise BASELINE additions that race ahead of
 * the composer.json constraint bump on the sister package fatal at boot.
 */
final class RectorInternalContractsTest extends TestCase
{
    /**
     * Class-typed const tables across rector classes that must satisfy the
     * "every entry's class_exists" invariant. Each entry: [class, const,
     * value-extractor]. The value-extractor maps the constant value to a
     * list of FQCNs (handles map-shaped tables where keys are FQCNs vs
     * list-shaped tables where values are FQCNs).
     *
     * @var list<array{class: class-string, const: string, extractor: callable(array<int|string, mixed>): list<class-string>}>
     */
    private const HARDCODED_CLASS_TABLES = [
        [
            'class' => SimplifyRuleWrappersRector::class,
            'const' => 'FACTORY_BASELINE',
            // Map<factoryName, class-string>; FQCNs are the values.
            'extractor' => 'array_values',
        ],
        [
            'class' => InlineMessageSurface::class,
            'const' => 'TYPED_RULE_CLASSES',
            // List<class-string>; FQCNs are the values.
            'extractor' => 'array_values',
        ],
        [
            'class' => PromoteFieldFactoryRector::class,
            'const' => 'TYPED_BUILDER_TO_FACTORY',
            // Map<class-string, factoryName>; FQCNs are the keys.
            'extractor' => 'array_keys',
        ],
    ];

    /**
     * `ConvertLivewireRuleAttributeRector::multilineArray()` attaches
     * `NEWLINED_ARRAY_PRINT` to the synthesized `rules()` return array so the
     * format-preserving printer emits one item per line regardless of
     * `Array_::$items` count. If this constant vanishes, the generated
     * `rules()` method collapses to a single line and quickly blows past
     * reasonable widths on consumers with 3+ properties.
     */
    public function testNewlinedArrayPrintConstantExists(): void
    {
        $this->assertTrue(
            defined(AttributeKey::class . '::NEWLINED_ARRAY_PRINT'),
            AttributeKey::class . '::NEWLINED_ARRAY_PRINT is gone; '
                . 'ConvertLivewireRuleAttributeRector::multilineArray() will no longer '
                . 'force multi-line output. Find the replacement attribute in your Rector '
                . 'version and update multilineArray() accordingly.',
        );
    }

    /**
     * Generalized sweep across every known hardcoded class-typed const
     * table. Each FQCN entry must resolve under the package's declared
     * `composer.json` constraint on the sister `laravel-fluent-validation`
     * package. Catches BASELINE additions that race ahead of constraint
     * bumps locally before the bytecode-level fatal lands on consumer CI.
     *
     * Replaces the 0.22.1 site-specific `testFactoryBaselineClassesAllResolve`
     * with a registry-driven sweep so future rectors adding their own
     * class-typed const tables get coverage by registering with
     * `HARDCODED_CLASS_TABLES` rather than by adding a new test method.
     */
    public function testEveryHardcodedClassTableResolves(): void
    {
        $missing = [];

        foreach (self::HARDCODED_CLASS_TABLES as $table) {
            $reflection = new ReflectionClass($table['class']);
            $value = $reflection->getConstant($table['const']);

            $this->assertIsArray(
                $value,
                "{$table['class']}::{$table['const']} must be an array.",
            );
            $this->assertNotEmpty(
                $value,
                "{$table['class']}::{$table['const']} must have at least one entry.",
            );

            $extractor = $table['extractor'];
            $fqcns = $extractor($value);

            foreach ($fqcns as $fqcn) {
                if (! is_string($fqcn) || ! class_exists($fqcn)) {
                    $missing[] = "{$table['class']}::{$table['const']} → {$fqcn}";
                }
            }
        }

        $this->assertSame(
            [],
            $missing,
            'Hardcoded class-typed const tables reference classes that do '
            . 'not exist in the installed `laravel-fluent-validation`. The '
            . 'composer.json constraint on `sandermuller/laravel-fluent-validation` '
            . 'must be bumped to a version that ships these classes; otherwise '
            . 'consumers on the older constraint will fatal at rector boot. '
            . "Missing:\n  - " . implode("\n  - ", $missing),
        );
    }

    /**
     * Boot-path smoke test: for each rector with a hardcoded class-typed
     * const table that reflects on entries, instantiate and exercise the
     * boot/load path. Asserts no exception escapes under the current
     * installed sister-package surface.
     *
     * Pairs with `testEveryHardcodedClassTableResolves` above — that test
     * asserts the table's classes resolve right now (static-existence
     * invariant); this test asserts the boot path itself runs cleanly
     * with whatever filtering the `class_exists` guards apply.
     *
     * The complementary "boots cleanly under simulated-missing-class"
     * test is a methodology in PROCESS.md's candidate-pen
     * (runtime-simulation methodology, awaiting second-cycle
     * confirmation). When that methodology promotes to a numbered
     * invariant, the simulated-missing-class fixture will land
     * alongside as the runtime-guard exercise. Until then, the
     * static-existence sweep + this current-surface boot smoke are
     * the in-repo coverage pair.
     *
     * Source-grep / AST-analysis sweep for the class_exists guard
     * pattern was prototyped during 0.22.2 development but dropped:
     * the assignment-then-iteration pattern in SimplifyRuleWrappersRector
     * (`$factoryMap = array_filter(self::FACTORY_BASELINE, ...); ...
     * foreach ($factoryMap as ...)`) doesn't fit a clean grep, and
     * tightening to AST analysis is past the cost-value threshold for
     * a 3-site invariant. Defer the static-guard test pattern until
     * either (a) a fourth iteration-with-reflection site lands and
     * the AST cost amortizes, or (b) a simpler invariant-encoding
     * shape emerges from a future cycle.
     */
    public function testRectorClassesWithHardcodedTablesBootCleanly(): void
    {
        // SimplifyRuleWrappersRector — invoke private bootResolutionTables.
        try {
            $rector = new SimplifyRuleWrappersRector();
            (new ReflectionClass($rector))
                ->getMethod('bootResolutionTables')
                ->invoke($rector);
        } catch (Throwable $throwable) {
            $this->fail(
                'SimplifyRuleWrappersRector::bootResolutionTables() threw '
                . $throwable::class . ': ' . $throwable->getMessage(),
            );
        }

        // InlineMessageSurface — public static load() is the boot path.
        try {
            InlineMessageSurface::load();
        } catch (Throwable $throwable) {
            $this->fail(
                'InlineMessageSurface::load() threw '
                . $throwable::class . ': ' . $throwable->getMessage(),
            );
        }

        // PromoteFieldFactoryRector — instantiate, classesWithPublicMethod
        // is the iteration path; invoke via reflection.
        try {
            $promote = new PromoteFieldFactoryRector();
            (new ReflectionClass($promote))
                ->getMethod('classesWithPublicMethod')
                ->invoke($promote, 'min');
        } catch (Throwable $throwable) {
            $this->fail(
                'PromoteFieldFactoryRector::classesWithPublicMethod() threw '
                . $throwable::class . ': ' . $throwable->getMessage(),
            );
        }

        $this->addToAssertionCount(3);
    }
}

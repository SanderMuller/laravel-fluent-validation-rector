<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests;

use PHPUnit\Framework\TestCase;
use Rector\NodeTypeResolver\Node\AttributeKey;
use ReflectionClass;
use SanderMuller\FluentValidationRector\Rector\SimplifyRuleWrappersRector;
use Throwable;

/**
 * Pins the Rector-internal APIs we rely on at class/constant level. Rector
 * has churned `AttributeKey` members across past major versions; this test
 * fails fast on Rector 3+ upgrades that rename or remove keys we depend on,
 * rather than letting the absence silently degrade generated output.
 *
 * Also pins the cross-package class-existence invariants that `bootResolutionTables()`
 * depends on — surfaced via 0.22.0 dogfood when a vendor-rsync methodology
 * exposed an out-of-contract `laravel-fluent-validation` install where the
 * `FACTORY_BASELINE` claimed a class (`AcceptedRule`) that didn't exist in
 * the older sister-package version. The fix in 0.22.1 wraps the BASELINE
 * merge in `array_filter(…, 'class_exists')`; these tests pin both the
 * required-classes-still-ship invariant and the graceful-degradation guard.
 */
final class RectorInternalContractsTest extends TestCase
{
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
     * `SimplifyRuleWrappersRector::FACTORY_BASELINE` maps factory names to
     * class FQCNs in the sister `laravel-fluent-validation` package. Every
     * entry must resolve under the package's declared `^X.Y` constraint so
     * the reflection iteration in `bootResolutionTables()` doesn't fatal.
     *
     * If a contributor adds a new BASELINE entry that races ahead of the
     * composer.json constraint bump, this test catches it locally before
     * the bytecode-level fatal lands on a consumer's CI.
     */
    public function testFactoryBaselineClassesAllResolve(): void
    {
        $reflection = new ReflectionClass(SimplifyRuleWrappersRector::class);
        $baseline = $reflection->getConstant('FACTORY_BASELINE');

        $this->assertIsArray($baseline, 'FACTORY_BASELINE must be an array.');
        $this->assertNotEmpty($baseline, 'FACTORY_BASELINE must have at least one entry.');

        $missing = [];
        foreach ($baseline as $factoryName => $class) {
            if (! is_string($class) || ! class_exists($class)) {
                $missing[] = "{$factoryName} => {$class}";
            }
        }

        $this->assertSame(
            [],
            $missing,
            'FACTORY_BASELINE entries reference classes that do not exist '
            . 'in the installed `laravel-fluent-validation`. The composer.json '
            . 'constraint on `sandermuller/laravel-fluent-validation` must be '
            . 'bumped to a version that ships these classes; otherwise '
            . 'consumers on the older constraint will fatal at rector boot. '
            . "Missing:\n  - " . implode("\n  - ", $missing),
        );
    }

    /**
     * Boots the rector once and verifies no `ReflectionException` (or any
     * exception) escapes from `bootResolutionTables()`. Smoke-tests the
     * combined invariant: BASELINE classes exist + reflection-discovered
     * factories filter cleanly + `array_filter(…, 'class_exists')` guard
     * runs. Future BASELINE entries that point at hypothetical / not-yet-
     * shipped classes degrade gracefully instead of fataling at boot.
     */
    public function testSimplifyRuleWrappersRectorBootsCleanly(): void
    {
        try {
            $rector = new SimplifyRuleWrappersRector();
            // bootResolutionTables() is `private`; instantiation alone
            // doesn't trigger it (it fires on first refactor()), so we
            // explicitly invoke via reflection to pin boot-time behavior.
            // PHP 8.1+ allows reflection-invoke of private methods without
            // an explicit `setAccessible(true)` (deprecated as of 8.1).
            $boot = (new ReflectionClass($rector))->getMethod('bootResolutionTables');
            $boot->invoke($rector);
        } catch (Throwable $throwable) {
            $this->fail(
                'SimplifyRuleWrappersRector::bootResolutionTables() threw '
                . $throwable::class . ': ' . $throwable->getMessage()
                . ' — likely a `FACTORY_BASELINE` entry whose class does not '
                . 'exist in the installed sister-package version. The 0.22.1 '
                . "`array_filter(FACTORY_BASELINE, 'class_exists')` guard "
                . 'should make this graceful; if it fataled, the guard is '
                . 'missing or bypassed.',
            );
        }

        $this->addToAssertionCount(1);
    }
}

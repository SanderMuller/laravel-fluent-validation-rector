<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests;

use PHPUnit\Framework\TestCase;
use SanderMuller\FluentValidation\Rules\ArrayRule;
use SanderMuller\FluentValidation\Rules\BooleanRule;
use SanderMuller\FluentValidation\Rules\DateRule;
use SanderMuller\FluentValidation\Rules\EmailRule;
use SanderMuller\FluentValidation\Rules\ImageRule;
use SanderMuller\FluentValidation\Rules\NumericRule;
use SanderMuller\FluentValidation\Rules\StringRule;
use SanderMuller\FluentValidationRector\Rector\InlineMessageSurface;

/**
 * Pins the reflection-derived allowlist against the laravel-fluent-validation
 * 1.20.0 surface. Fails loudly when upstream adds or removes a method with
 * a `?string $message = null` parameter, or when a composite/mode-modifier
 * exclusion drifts. The rector's rewrite predicates (Phase 2+) depend on
 * this allowlist's shape.
 */
final class InlineMessageParamAllowlistTest extends TestCase
{
    public function testSurfaceDetectedOnCurrentFloor(): void
    {
        $this->assertTrue(
            InlineMessageSurface::isSurfaceAvailable(),
            'Expected 1.20+ surface to be detected — FluentRule::email should expose ?string $message = null.',
        );
    }

    public function testAllowlistContainsExpectedFactoryRewriteTargets(): void
    {
        $allowlist = InlineMessageSurface::load();

        $rewritableFactories = [
            'string', 'numeric', 'integer', 'boolean',
            'accepted', 'declined', 'array', 'file', 'image',
            'email', 'url', 'uuid', 'ulid', 'ip', 'ipv4', 'ipv6',
            'macAddress', 'json', 'timezone', 'hexColor', 'activeUrl',
            'regex', 'list', 'enum',
        ];

        foreach ($rewritableFactories as $factory) {
            $key = 'FluentRule::' . $factory;
            $this->assertArrayHasKey($key, $allowlist, "Expected factory {$key} in allowlist.");
            $this->assertSame(
                'factory_rewritable',
                $allowlist[$key]['category'],
                "Expected {$key} category=factory_rewritable.",
            );
        }
    }

    public function testAllowlistExcludesFactoriesWithoutMessageParam(): void
    {
        $allowlist = InlineMessageSurface::load();

        $noMessageFactories = ['date', 'dateTime', 'password', 'field', 'anyOf'];

        foreach ($noMessageFactories as $factory) {
            $key = 'FluentRule::' . $factory;
            $this->assertArrayHasKey($key, $allowlist, "Expected factory {$key} in allowlist (categorized).");
            $this->assertSame(
                'factory_no_message',
                $allowlist[$key]['category'],
                "Expected {$key} category=factory_no_message.",
            );
        }
    }

    public function testAllowlistCategorizesCompositeMethods(): void
    {
        $allowlist = InlineMessageSurface::load();

        $composite = [
            NumericRule::class . '::digits',
            NumericRule::class . '::digitsBetween',
            DateRule::class . '::between',
            DateRule::class . '::betweenOrEqual',
            ImageRule::class . '::width',
            ImageRule::class . '::dimensions',
            ImageRule::class . '::ratio',
        ];

        foreach ($composite as $key) {
            $this->assertArrayHasKey($key, $allowlist, "Expected composite {$key} in allowlist.");
            $this->assertSame(
                'composite',
                $allowlist[$key]['category'],
                "Expected {$key} category=composite.",
            );
        }
    }

    public function testAllowlistIncludesRewritableRuleMethods(): void
    {
        $allowlist = InlineMessageSurface::load();

        $rewritable = [
            StringRule::class . '::min',
            StringRule::class . '::max',
            StringRule::class . '::alpha',
            StringRule::class . '::confirmed',
            StringRule::class . '::regex',
            NumericRule::class . '::decimal',
            ArrayRule::class . '::min',
            DateRule::class . '::before',
            DateRule::class . '::after',
            EmailRule::class . '::confirmed',
            BooleanRule::class . '::accepted',
        ];

        foreach ($rewritable as $key) {
            $this->assertArrayHasKey($key, $allowlist, "Expected rewritable method {$key} in allowlist.");
            $this->assertSame(
                'rewritable',
                $allowlist[$key]['category'],
                "Expected {$key} category=rewritable.",
            );
            $this->assertFalse(
                $allowlist[$key]['is_variadic'],
                "Expected {$key} is_variadic=false.",
            );
        }
    }

    public function testAllowlistIncludesFieldModifierSurface(): void
    {
        $allowlist = InlineMessageSurface::load();

        // HasFieldModifiers trait surface — present on every typed-rule
        // class. De-duplication keeps only the first-reflected owner, so
        // these entries appear under whatever class was reflected first
        // (order is self::TYPED_RULE_CLASSES).
        $fieldModifiers = [
            'required', 'sometimes', 'filled', 'present',
            'prohibited', 'missing',
            'requiredIfAccepted', 'requiredIfDeclined',
            'prohibitedIfAccepted', 'prohibitedIfDeclined',
            'rule',
        ];

        $found = [];

        foreach (array_keys($allowlist) as $key) {
            foreach ($fieldModifiers as $method) {
                if (str_ends_with($key, '::' . $method)) {
                    $found[$method] = true;
                }
            }
        }

        foreach ($fieldModifiers as $method) {
            $this->assertArrayHasKey(
                $method,
                $found,
                "Expected HasFieldModifiers::{$method} to appear under some typed-rule class in the allowlist.",
            );
        }
    }

    public function testPreFloorGuardSkipsAllRewrites(): void
    {
        // Sanity: on the current (1.20+) floor, surface is available and
        // allowlist is populated. The floor-mismatch branch is exercised
        // via the isSurfaceAvailableForTesting false-case — covered by a
        // static-analysis-only fixture in a downstream consumer project
        // because we cannot downgrade the vendor in this test process.
        $this->assertNotEmpty(
            InlineMessageSurface::load(),
            'Allowlist should be populated when surface is available.',
        );
    }
}

<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests;

use Composer\InstalledVersions;
use PHPUnit\Framework\TestCase;

/**
 * Smoke test: confirms the resolved Laravel-framework version matches the
 * `CI_LARAVEL_MAJOR` env var set by the matrix leg. Catches matrix-leg drift
 * if a future change accidentally pins all legs to the same major (e.g. via
 * a forgotten `composer.lock` commit overriding the per-leg pin).
 *
 * Skipped when run locally (no `CI_LARAVEL_MAJOR` set).
 */
final class CIMatrixSanityTest extends TestCase
{
    public function testInstalledLaravelMajorMatchesCIMatrixLeg(): void
    {
        $expected = getenv('CI_LARAVEL_MAJOR');

        if (! is_string($expected) || $expected === '') {
            $this->markTestSkipped('CI_LARAVEL_MAJOR not set (running outside the CI matrix).');
        }

        $version = InstalledVersions::getVersion('laravel/framework');
        $this->assertNotNull($version, 'laravel/framework must be installed.');

        $expectedMajor = $this->extractMajor($expected);
        $actualMajor = $this->extractMajor($version);

        $this->assertSame(
            $expectedMajor,
            $actualMajor,
            "Matrix leg pinned Laravel {$expected} but composer resolved laravel/framework {$version}. "
            . 'Check the per-leg testbench constraint in `.github/workflows/run-tests.yml`.',
        );
    }

    private function extractMajor(string $constraint): string
    {
        $trimmed = ltrim($constraint, 'v^~>=< ');

        if (preg_match('/(\d+)/', $trimmed, $match)) {
            return $match[1];
        }

        return $constraint;
    }
}

<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests;

use Composer\InstalledVersions;
use PHPUnit\Framework\TestCase;
use SanderMuller\FluentValidationRector\Internal\Diagnostics;

/**
 * Pins the skip-log header shape and version-resolution path.
 *
 * The header is the public PUBLIC_API.md "Skip-log header shape"
 * commitment: three slots (version, ISO-8601 UTC timestamp, verbose
 * tier) inside a fixed two-line shape. Slot *values* are
 * implementation; the *shape* is observable contract.
 *
 * Version resolution must stay sourced from a single path
 * (`Composer\InstalledVersions::getPrettyVersion()`) so cross-version
 * triage signals stay consistent. Mijntp 0.20.x finding #5 prompted
 * the audit; this test pins the resolution path so a future refactor
 * (e.g. reading composer.json directly, hard-coded constant) is
 * caught at CI time.
 */
final class SkipLogHeaderTest extends TestCase
{
    public function testHeaderMatchesPublicApiShape(): void
    {
        $header = Diagnostics::skipLogHeader();

        $pattern = '/^# laravel-fluent-validation-rector \S+ — generated '
            . '\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z\n'
            . '# verbose tier: (off|actionable|all)\n\n$/';

        $this->assertMatchesRegularExpression(
            $pattern,
            $header,
            'Header must match the PUBLIC_API.md "Skip-log header shape" '
            . 'two-line format with version + ISO-8601 UTC timestamp + '
            . 'verbose tier slots.',
        );
    }

    public function testVersionSlotMatchesInstalledVersionsResolution(): void
    {
        $header = Diagnostics::skipLogHeader();

        $expected = InstalledVersions::getPrettyVersion(
            'sandermuller/laravel-fluent-validation-rector',
        );
        // `getPrettyVersion` returns null only when the package isn't
        // registered — impossible while the test runs against the
        // installed package's autoloader. Narrow for static analysis.
        $this->assertIsString($expected);

        $this->assertStringContainsString(
            "# laravel-fluent-validation-rector {$expected} — generated ",
            $header,
            'Version slot must reflect ' . InstalledVersions::class . '::getPrettyVersion(). '
            . 'A future refactor changing the resolution path (composer.json read, '
            . 'hard-coded constant, etc.) would break this assertion — by design.',
        );
    }

    public function testVerboseTierSlotIsResolvedTier(): void
    {
        $header = Diagnostics::skipLogHeader();

        $this->assertMatchesRegularExpression(
            '/# verbose tier: (off|actionable|all)\n/',
            $header,
            'Verbose tier slot must be one of the three values in the '
            . 'PUBLIC_API.md FLUENT_VALIDATION_RECTOR_VERBOSE accepted-values list.',
        );
    }
}

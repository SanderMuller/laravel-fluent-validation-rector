<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests;

use PHPUnit\Framework\TestCase;
use SanderMuller\FluentValidationRector\Diagnostics;

final class DiagnosticsTest extends TestCase
{
    private ?string $originalVerbose;

    protected function setUp(): void
    {
        parent::setUp();

        $env = getenv(Diagnostics::VERBOSE_ENV);
        $this->originalVerbose = $env === false ? null : $env;
        putenv(Diagnostics::VERBOSE_ENV);
        unset($_SERVER[Diagnostics::VERBOSE_ENV], $_ENV[Diagnostics::VERBOSE_ENV]);
    }

    protected function tearDown(): void
    {
        if ($this->originalVerbose === null) {
            putenv(Diagnostics::VERBOSE_ENV);
            unset($_SERVER[Diagnostics::VERBOSE_ENV], $_ENV[Diagnostics::VERBOSE_ENV]);
        } else {
            putenv(Diagnostics::VERBOSE_ENV . '=' . $this->originalVerbose);
        }

        parent::tearDown();
    }

    public function testOffByDefault(): void
    {
        $this->assertFalse(Diagnostics::isVerbose());
    }

    public function testEnvVarEnablesVerbose(): void
    {
        putenv(Diagnostics::VERBOSE_ENV . '=1');

        $this->assertTrue(Diagnostics::isVerbose());
    }

    public function testSuperglobalsDoNotEnableVerbose(): void
    {
        // OS env is the single source of truth — `$_SERVER` / `$_ENV`
        // mutations from userland bootstrap (e.g. Dotenv) must NOT flip
        // verbose on, since parallel workers only inherit OS env and would
        // otherwise disagree with the parent on the log path.
        $_SERVER[Diagnostics::VERBOSE_ENV] = '1';
        $_ENV[Diagnostics::VERBOSE_ENV] = '1';

        $this->assertFalse(Diagnostics::isVerbose());
    }

    public function testOffModePathUnderSystemTempDir(): void
    {
        $path = Diagnostics::skipLogPath();

        $this->assertStringStartsWith(sys_get_temp_dir(), $path);
        $this->assertStringNotContainsString(Diagnostics::VERBOSE_LOG_FILENAME, $path);
    }

    public function testVerboseModePathInCacheSubdirOfCwd(): void
    {
        putenv(Diagnostics::VERBOSE_ENV . '=1');

        $cwd = getcwd();
        $this->assertNotFalse($cwd);
        $this->assertSame(
            $cwd . '/' . Diagnostics::VERBOSE_LOG_DIR . '/' . Diagnostics::VERBOSE_LOG_FILENAME,
            Diagnostics::skipLogPath(),
        );
    }

    public function testSentinelSitsBesideLog(): void
    {
        $this->assertSame(Diagnostics::skipLogPath() . '.session', Diagnostics::skipLogSentinelPath());
    }

    public function testVerbosityTierOffWhenEnvUnset(): void
    {
        $this->assertSame(Diagnostics::TIER_OFF, Diagnostics::verbosityTier());
    }

    public function testVerbosityTierOffWhenEnvEmpty(): void
    {
        putenv(Diagnostics::VERBOSE_ENV . '=');

        $this->assertSame(Diagnostics::TIER_OFF, Diagnostics::verbosityTier());
    }

    public function testVerbosityTierActionable(): void
    {
        putenv(Diagnostics::VERBOSE_ENV . '=actionable');

        $this->assertSame(Diagnostics::TIER_ACTIONABLE, Diagnostics::verbosityTier());
        $this->assertFalse(Diagnostics::isVerbose(), 'actionable tier is not legacy verbose');
    }

    public function testVerbosityTierActionableCaseInsensitive(): void
    {
        putenv(Diagnostics::VERBOSE_ENV . '=Actionable');

        $this->assertSame(Diagnostics::TIER_ACTIONABLE, Diagnostics::verbosityTier());
    }

    public function testVerbosityTierAllFromLegacyOne(): void
    {
        putenv(Diagnostics::VERBOSE_ENV . '=1');

        $this->assertSame(Diagnostics::TIER_ALL, Diagnostics::verbosityTier());
        $this->assertTrue(Diagnostics::isVerbose(), 'legacy =1 must keep isVerbose() true');
    }

    public function testVerbosityTierAllFromTrueString(): void
    {
        putenv(Diagnostics::VERBOSE_ENV . '=true');

        $this->assertSame(Diagnostics::TIER_ALL, Diagnostics::verbosityTier());
    }

    public function testVerbosityTierAllFromAllString(): void
    {
        putenv(Diagnostics::VERBOSE_ENV . '=all');

        $this->assertSame(Diagnostics::TIER_ALL, Diagnostics::verbosityTier());
    }

    public function testActionableTierUsesCacheSubdirLogPath(): void
    {
        putenv(Diagnostics::VERBOSE_ENV . '=actionable');

        $cwd = getcwd();
        $this->assertNotFalse($cwd);
        $this->assertSame(
            $cwd . '/' . Diagnostics::VERBOSE_LOG_DIR . '/' . Diagnostics::VERBOSE_LOG_FILENAME,
            Diagnostics::skipLogPath(),
        );
    }
}

<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\Concerns;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use SanderMuller\FluentValidationRector\Diagnostics;
use SanderMuller\FluentValidationRector\Rector\Concerns\LogsSkipReasons;
use SanderMuller\FluentValidationRector\Tests\Concerns\Support\LogsSkipReasonsHarness;

final class LogsSkipReasonsTest extends TestCase
{
    private string $tempDir;

    private string $originalCwd;

    private ?string $originalVerbose;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalCwd = getcwd() ?: sys_get_temp_dir();
        $this->tempDir = sys_get_temp_dir() . '/logs-skip-reasons-test-' . uniqid('', true);

        if (! mkdir($this->tempDir) && ! is_dir($this->tempDir)) {
            $this->fail('Failed to create temp dir for LogsSkipReasons test');
        }

        chdir($this->tempDir);
        $this->tempDir = (string) getcwd();

        $env = getenv(Diagnostics::VERBOSE_ENV);
        $this->originalVerbose = $env === false ? null : $env;
        putenv(Diagnostics::VERBOSE_ENV);
        unset($_SERVER[Diagnostics::VERBOSE_ENV], $_ENV[Diagnostics::VERBOSE_ENV]);

        $this->resetDedupState();
    }

    protected function tearDown(): void
    {
        foreach (Diagnostics::allSkipLogArtifacts() as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        chdir($this->originalCwd);

        if (is_dir($this->tempDir)) {
            @rmdir($this->tempDir);
        }

        if ($this->originalVerbose === null) {
            putenv(Diagnostics::VERBOSE_ENV);
            unset($_SERVER[Diagnostics::VERBOSE_ENV], $_ENV[Diagnostics::VERBOSE_ENV]);
        } else {
            putenv(Diagnostics::VERBOSE_ENV . '=' . $this->originalVerbose);
        }

        $this->resetDedupState();

        parent::tearDown();
    }

    public function testVerboseOnlyEntrySilencedWhenVerboseOff(): void
    {
        $this->assertFalse(Diagnostics::isVerbose());

        $harness = new LogsSkipReasonsHarness($this->tempDir . '/SomeClass.php');
        $harness->callLogSkipByName('App\\SomeClass', 'already has trait', verboseOnly: true);

        $logPath = Diagnostics::skipLogPath();
        $this->assertFalse(
            is_file($logPath) && filesize($logPath) > 0,
            'verbose-only skip must not write to the log when verbose is off',
        );
    }

    public function testVerboseOnlyEntryWrittenWhenVerboseOn(): void
    {
        putenv(Diagnostics::VERBOSE_ENV . '=1');
        $this->assertTrue(Diagnostics::isVerbose());

        $harness = new LogsSkipReasonsHarness($this->tempDir . '/SomeClass.php');
        $harness->callLogSkipByName('App\\SomeClass', 'already has trait', verboseOnly: true);

        $logPath = Diagnostics::skipLogPath();
        $this->assertFileExists($logPath);
        $this->assertStringContainsString('App\\SomeClass', (string) file_get_contents($logPath));
        $this->assertStringContainsString('already has trait', (string) file_get_contents($logPath));
    }

    public function testDefaultSkipStillWrittenWhenVerboseOff(): void
    {
        $this->assertFalse(Diagnostics::isVerbose());

        $harness = new LogsSkipReasonsHarness($this->tempDir . '/SomeClass.php');
        $harness->callLogSkipByName('App\\SomeClass', 'actionable skip');

        $logPath = Diagnostics::skipLogPath();
        $this->assertFileExists($logPath);
        $this->assertStringContainsString('actionable skip', (string) file_get_contents($logPath));
    }

    public function testActionableTierSurfacesActionableVerboseOnlyEntry(): void
    {
        putenv(Diagnostics::VERBOSE_ENV . '=actionable');
        $this->assertSame(Diagnostics::TIER_ACTIONABLE, Diagnostics::verbosityTier());

        $harness = new LogsSkipReasonsHarness($this->tempDir . '/SomeClass.php');
        $harness->callLogSkipByName(
            'App\\SomeClass',
            'rule payload not statically resolvable',
            verboseOnly: true,
            actionable: true,
        );

        $logPath = Diagnostics::skipLogPath();
        $this->assertFileExists($logPath);
        $this->assertStringContainsString('rule payload not statically resolvable', (string) file_get_contents($logPath));
    }

    public function testActionableTierHidesNonActionableVerboseOnlyEntry(): void
    {
        putenv(Diagnostics::VERBOSE_ENV . '=actionable');
        $this->assertSame(Diagnostics::TIER_ACTIONABLE, Diagnostics::verbosityTier());

        $harness = new LogsSkipReasonsHarness($this->tempDir . '/SomeClass.php');
        $harness->callLogSkipByName(
            'App\\SomeClass',
            'already has trait',
            verboseOnly: true,
            actionable: false,
        );

        $logPath = Diagnostics::skipLogPath();
        $this->assertFalse(
            is_file($logPath) && filesize($logPath) > 0,
            'non-actionable verbose-only entry must not surface in actionable tier',
        );
    }

    public function testActionableTierStillWritesDefaultSkips(): void
    {
        putenv(Diagnostics::VERBOSE_ENV . '=actionable');

        $harness = new LogsSkipReasonsHarness($this->tempDir . '/SomeClass.php');
        $harness->callLogSkipByName('App\\SomeClass', 'default bug-mode skip');

        $logPath = Diagnostics::skipLogPath();
        $this->assertFileExists($logPath);
        $this->assertStringContainsString('default bug-mode skip', (string) file_get_contents($logPath));
    }

    public function testAllTierSurfacesNonActionableVerboseOnlyEntry(): void
    {
        putenv(Diagnostics::VERBOSE_ENV . '=all');
        $this->assertSame(Diagnostics::TIER_ALL, Diagnostics::verbosityTier());

        $harness = new LogsSkipReasonsHarness($this->tempDir . '/SomeClass.php');
        $harness->callLogSkipByName(
            'App\\SomeClass',
            'already has trait',
            verboseOnly: true,
            actionable: false,
        );

        $logPath = Diagnostics::skipLogPath();
        $this->assertFileExists($logPath);
        $this->assertStringContainsString('already has trait', (string) file_get_contents($logPath));
    }

    public function testLegacyOneValueStillWritesEverything(): void
    {
        putenv(Diagnostics::VERBOSE_ENV . '=1');
        $this->assertSame(Diagnostics::TIER_ALL, Diagnostics::verbosityTier());

        $harness = new LogsSkipReasonsHarness($this->tempDir . '/SomeClass.php');
        $harness->callLogSkipByName(
            'App\\SomeClass',
            'already has trait',
            verboseOnly: true,
            actionable: false,
        );

        $logPath = Diagnostics::skipLogPath();
        $this->assertFileExists($logPath);
        $this->assertStringContainsString('already has trait', (string) file_get_contents($logPath));
    }

    private function resetDedupState(): void
    {
        $reflection = new ReflectionClass(LogsSkipReasons::class);

        // Trait statics live on the using class, not the trait itself — reset
        // via the harness so each test sees a clean de-dup set and fresh
        // session-verification flag.
        $harnessClass = new ReflectionClass(LogsSkipReasonsHarness::class);

        foreach (['loggedSkips', 'logSessionVerified'] as $propName) {
            if ($harnessClass->hasProperty($propName)) {
                $prop = $harnessClass->getProperty($propName);
                $prop->setValue(null, $propName === 'loggedSkips' ? [] : false);
            }
        }

        unset($reflection);
    }
}

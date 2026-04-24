<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests;

use PHPUnit\Framework\TestCase;
use SanderMuller\FluentValidationRector\Diagnostics;
use SanderMuller\FluentValidationRector\RunSummary;

/**
 * Verifies the end-of-run summary emit for the skip log. Covers both verbose
 * and default (off) modes — the two produce different line shapes and
 * resolve the log to different paths (see `Diagnostics::skipLogPath()`).
 *
 * The production code registers a shutdown handler from `config/config.php`;
 * these tests exercise `format()` directly to avoid depending on PHP's
 * shutdown lifecycle.
 */
final class RunSummaryTest extends TestCase
{
    private string $tempDir;

    private string $originalCwd;

    private ?string $originalVerbose;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalCwd = getcwd() ?: sys_get_temp_dir();
        $this->tempDir = sys_get_temp_dir() . '/run-summary-test-' . uniqid('', true);

        if (! mkdir($this->tempDir) && ! is_dir($this->tempDir)) {
            $this->fail('Failed to create temp dir for RunSummary test');
        }

        chdir($this->tempDir);

        // Canonicalize via `getcwd()` (NOT `realpath()`) so the path
        // matches what `Diagnostics::skipLogPath()` emits when it calls
        // `getcwd()` itself. On Windows-CI runners the temp dir is under
        // `RUNNER~1` (8.3 short name); `realpath()` would resolve that to
        // the long-form `runneradmin`, but `getcwd()` keeps the short
        // form — the two would diverge and break the path-contains
        // assertions. Stick with `getcwd()` so both call sites produce
        // identical strings.
        $this->tempDir = (string) getcwd();

        $env = getenv(Diagnostics::VERBOSE_ENV);
        $this->originalVerbose = $env === false ? null : $env;
        putenv(Diagnostics::VERBOSE_ENV);
        unset($_SERVER[Diagnostics::VERBOSE_ENV], $_ENV[Diagnostics::VERBOSE_ENV]);
    }

    protected function tearDown(): void
    {
        $this->cleanupLogs();

        chdir($this->originalCwd);

        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }

        if ($this->originalVerbose === null) {
            putenv(Diagnostics::VERBOSE_ENV);
            unset($_SERVER[Diagnostics::VERBOSE_ENV], $_ENV[Diagnostics::VERBOSE_ENV]);
        } else {
            putenv(Diagnostics::VERBOSE_ENV . '=' . $this->originalVerbose);
        }

        parent::tearDown();
    }

    private function cleanupLogs(): void
    {
        foreach (Diagnostics::allSkipLogArtifacts() as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        putenv(Diagnostics::VERBOSE_ENV);
        unset($_SERVER[Diagnostics::VERBOSE_ENV], $_ENV[Diagnostics::VERBOSE_ENV]);
    }

    public function testFormatReturnsNullWhenLogAbsent(): void
    {
        $this->assertNull(RunSummary::format());
    }

    public function testFormatReturnsNullWhenLogEmpty(): void
    {
        file_put_contents(Diagnostics::skipLogPath(), '');

        $this->assertNull(RunSummary::format());
    }

    public function testVerboseModeReturnsSingularForOneEntry(): void
    {
        putenv(Diagnostics::VERBOSE_ENV . '=1');
        file_put_contents(Diagnostics::skipLogPath(), "[fluent-validation:skip] one entry\n");

        $line = RunSummary::format();

        $this->assertNotNull($line);
        $this->assertStringContainsString('1 skip entry', $line);
        $this->assertStringContainsString(Diagnostics::VERBOSE_LOG_FILENAME, $line);
        $this->assertStringContainsString('see for details', $line);
    }

    public function testVerboseModeReturnsPluralForMultipleEntries(): void
    {
        putenv(Diagnostics::VERBOSE_ENV . '=1');
        file_put_contents(
            Diagnostics::skipLogPath(),
            "[fluent-validation:skip] entry one\n[fluent-validation:skip] entry two\n[fluent-validation:skip] entry three\n",
        );

        $line = RunSummary::format();

        $this->assertNotNull($line);
        $this->assertStringContainsString('3 skip entries', $line);
        $this->assertStringContainsString(Diagnostics::VERBOSE_LOG_FILENAME, $line);
    }

    public function testVerboseModeKeepsLogOnDiskAfterFormatting(): void
    {
        putenv(Diagnostics::VERBOSE_ENV . '=1');
        $logPath = Diagnostics::skipLogPath();
        file_put_contents($logPath, "[fluent-validation:skip] one entry\n");

        RunSummary::format();

        $this->assertFileExists($logPath, 'Verbose mode must leave the log for the user to inspect.');
    }

    public function testActionableTierEmitsFilePathLine(): void
    {
        putenv(Diagnostics::VERBOSE_ENV . '=actionable');

        $logPath = Diagnostics::skipLogPath();
        $this->assertSame(
            getcwd() . '/' . Diagnostics::VERBOSE_LOG_FILENAME,
            $logPath,
            'Actionable tier must surface the log in cwd, like verbose mode.',
        );

        file_put_contents($logPath, "[fluent-validation:skip] actionable entry\n");

        $line = RunSummary::format();

        $this->assertNotNull($line);
        $this->assertStringContainsString('1 skip entry', $line);
        $this->assertStringContainsString(Diagnostics::VERBOSE_LOG_FILENAME, $line);
        $this->assertStringContainsString('see for details', $line);
        $this->assertStringNotContainsString('Re-run with', $line, 'Any opt-in tier already points at the file — no re-run hint needed.');
    }

    public function testDefaultModeEmitsHint(): void
    {
        $logPath = Diagnostics::skipLogPath();

        $this->assertStringContainsString(sys_get_temp_dir(), $logPath, 'Off-mode log must live under the system temp dir, not cwd.');

        file_put_contents($logPath, "[fluent-validation:skip] hidden entry\n[fluent-validation:skip] another\n");

        $line = RunSummary::format();

        $this->assertNotNull($line);
        $this->assertStringContainsString('2 skip entries', $line);
        $this->assertStringContainsString('Re-run with ' . Diagnostics::VERBOSE_ENV . '=actionable', $line);
        $this->assertStringContainsString('--clear-cache', $line, 'Hint must mention --clear-cache since bail results are cached per-file.');
        $this->assertStringNotContainsString('see for details', $line, 'Off-mode line must not reference a file path.');
    }

    public function testFormatIsPureAndIdempotent(): void
    {
        // `format()` must not mutate the log — cleanup belongs to the
        // shutdown closure in `registerShutdownHandler`, called after emit.
        $logPath = Diagnostics::skipLogPath();
        file_put_contents($logPath, "[fluent-validation:skip] one\n");

        $first = RunSummary::format();
        $second = RunSummary::format();

        $this->assertSame($first, $second);
        $this->assertFileExists($logPath, "format() must not unlink the log; that is the shutdown closure's job.");
    }

    public function testDefaultModeSingularNoun(): void
    {
        file_put_contents(Diagnostics::skipLogPath(), "[fluent-validation:skip] lonely\n");

        $line = RunSummary::format();

        $this->assertNotNull($line);
        $this->assertStringContainsString('1 skip entry.', $line);
    }

    public function testShouldRegisterRectorParentInvocation(): void
    {
        $this->assertTrue(RunSummary::shouldRegister(['/path/to/vendor/bin/rector', 'process']));
        $this->assertTrue(RunSummary::shouldRegister(['rector', 'process', '--dry-run']));
        $this->assertTrue(RunSummary::shouldRegister(['rector.phar', 'process']));
    }

    public function testShouldNotRegisterRectorWorkerInvocation(): void
    {
        // Workers get `--identifier <uuid>` appended by Rector's parallel executor.
        $this->assertFalse(RunSummary::shouldRegister(['/path/to/vendor/bin/rector', 'worker', '--identifier', 'abc123']));
    }

    public function testShouldNotRegisterNonRectorInvocation(): void
    {
        // Rule constructors fire in consumer test suites too; we don't want the
        // summary line leaking into pest/phpunit/phpstan/composer output.
        $this->assertFalse(RunSummary::shouldRegister(['/path/to/vendor/bin/pest']));
        $this->assertFalse(RunSummary::shouldRegister(['phpunit', '--testsuite', 'Unit']));
        $this->assertFalse(RunSummary::shouldRegister(['phpstan', 'analyse']));
        $this->assertFalse(RunSummary::shouldRegister(['composer', 'install']));
        $this->assertFalse(RunSummary::shouldRegister(['php', 'some-script.php']));
    }

    public function testShouldNotRegisterEmptyArgv(): void
    {
        $this->assertFalse(RunSummary::shouldRegister([]));
    }

    public function testUnlinkLogArtifactsRemovesBothFiles(): void
    {
        $logPath = Diagnostics::skipLogPath();
        $sentinelPath = Diagnostics::skipLogSentinelPath();

        file_put_contents($logPath, "[fluent-validation:skip] stale entry from prior run\n");
        file_put_contents($sentinelPath, 'ppid:99999');

        RunSummary::unlinkLogArtifacts();

        $this->assertFileDoesNotExist($logPath);
        $this->assertFileDoesNotExist($sentinelPath);
    }

    public function testUnlinkLogArtifactsRemovesBothFilesInVerboseMode(): void
    {
        putenv(Diagnostics::VERBOSE_ENV . '=1');

        $logPath = Diagnostics::skipLogPath();
        $sentinelPath = Diagnostics::skipLogSentinelPath();

        $this->assertStringContainsString($this->tempDir, $logPath, 'Verbose-mode path must resolve to cwd (tempDir in tests).');

        file_put_contents($logPath, "[fluent-validation:skip] stale verbose entry\n");
        file_put_contents($sentinelPath, 'ppid:99999');

        RunSummary::unlinkLogArtifacts();

        $this->assertFileDoesNotExist($logPath);
        $this->assertFileDoesNotExist($sentinelPath);
    }

    public function testUnlinkLogArtifactsClearsLegacyCwdLogInDefaultMode(): void
    {
        // Regression for the 0.4.x→0.5.0 upgrade path: consumer arrives
        // with a verbose-era `.rector-fluent-validation-skips.log` in the
        // project root (or toggles verbose off after a verbose run). The
        // cleanup must reach the cwd artifact even though current mode is
        // off (skipLogPath points to /tmp).
        $legacyLog = $this->tempDir . '/' . Diagnostics::VERBOSE_LOG_FILENAME;
        $legacySentinel = $legacyLog . '.session';

        file_put_contents($legacyLog, "[fluent-validation:skip] from a 0.4.x run\n");
        file_put_contents($legacySentinel, 'ppid:12345');

        RunSummary::unlinkLogArtifacts();

        $this->assertFileDoesNotExist($legacyLog, 'Legacy cwd log must be cleaned in default mode — that is the whole point of 0.5.0.');
        $this->assertFileDoesNotExist($legacySentinel);
    }

    public function testUnlinkLogArtifactsClearsQuietResidueInVerboseMode(): void
    {
        // Inverse scenario: user flips verbose on after running in default
        // mode. The prior run's tmp log should be cleared on the next
        // verbose run's parent init so it doesn't pollute /tmp.
        putenv(Diagnostics::VERBOSE_ENV . '=1');

        $quietLog = Diagnostics::quietLogPath();
        $quietSentinel = $quietLog . '.session';

        file_put_contents($quietLog, "[fluent-validation:skip] off-mode residue\n");
        file_put_contents($quietSentinel, 'ppid:12345');

        RunSummary::unlinkLogArtifacts();

        $this->assertFileDoesNotExist($quietLog);
        $this->assertFileDoesNotExist($quietSentinel);
    }

    public function testUnlinkLogArtifactsNoOpWhenAbsent(): void
    {
        // Must not raise warnings when files don't exist — this is the
        // cold-start case on a fresh machine where no prior run left
        // anything behind.
        RunSummary::unlinkLogArtifacts();

        $this->assertFileDoesNotExist(Diagnostics::skipLogPath());
    }

    public function testFormatReturnsNullAfterUnlinkLogArtifacts(): void
    {
        // Regression: zero-skip run with a stale log from a prior invocation
        // must not emit a phantom hint. Parent `registerShutdownHandler`
        // calls `unlinkLogArtifacts` before workers spawn; simulate that here
        // and verify `format()` sees nothing.
        file_put_contents(Diagnostics::skipLogPath(), "[fluent-validation:skip] phantom\n");

        RunSummary::unlinkLogArtifacts();

        $this->assertNull(RunSummary::format());
    }
}

<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests;

use PHPUnit\Framework\TestCase;
use SanderMuller\FluentValidationRector\RunSummary;

/**
 * Verifies the end-of-run summary emit for `.rector-fluent-validation-skips.log`.
 * The production code registers a shutdown handler from `config/config.php`;
 * these tests exercise `format()` directly to avoid depending on PHP's
 * shutdown lifecycle.
 */
final class RunSummaryTest extends TestCase
{
    private string $tempDir;

    private string $originalCwd;

    private string $logPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalCwd = getcwd() ?: sys_get_temp_dir();
        $this->tempDir = sys_get_temp_dir() . '/run-summary-test-' . uniqid('', true);

        if (! mkdir($this->tempDir) && ! is_dir($this->tempDir)) {
            $this->fail('Failed to create temp dir for RunSummary test');
        }

        chdir($this->tempDir);

        $this->logPath = $this->tempDir . '/.rector-fluent-validation-skips.log';
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);

        if (is_file($this->logPath)) {
            unlink($this->logPath);
        }

        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }

        parent::tearDown();
    }

    public function testFormatReturnsNullWhenLogAbsent(): void
    {
        $this->assertNull(RunSummary::format());
    }

    public function testFormatReturnsNullWhenLogEmpty(): void
    {
        file_put_contents($this->logPath, '');

        $this->assertNull(RunSummary::format());
    }

    public function testFormatReturnsSingularNounForOneEntry(): void
    {
        file_put_contents($this->logPath, "[fluent-validation:skip] one entry\n");

        $line = RunSummary::format();

        $this->assertNotNull($line);
        $this->assertStringContainsString('1 skip entry', $line);
        $this->assertStringContainsString('.rector-fluent-validation-skips.log', $line);
    }

    public function testFormatReturnsPluralNounForMultipleEntries(): void
    {
        file_put_contents($this->logPath, "[fluent-validation:skip] entry one\n[fluent-validation:skip] entry two\n[fluent-validation:skip] entry three\n");

        $line = RunSummary::format();

        $this->assertNotNull($line);
        $this->assertStringContainsString('3 skip entries', $line);
        $this->assertStringContainsString('.rector-fluent-validation-skips.log', $line);
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
}

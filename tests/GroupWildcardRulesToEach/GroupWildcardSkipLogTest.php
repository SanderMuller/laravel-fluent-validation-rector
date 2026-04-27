<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach;

use Iterator;
use PHPUnit\Framework\Attributes\DataProvider;
use Rector\Testing\PHPUnit\AbstractRectorTestCase;
use ReflectionClass;
use SanderMuller\FluentValidationRector\Diagnostics;
use SanderMuller\FluentValidationRector\Rector\Concerns\LogsSkipReasons;
use SanderMuller\FluentValidationRector\Rector\GroupWildcardRulesToEachRector;

/**
 * Regression coverage for the skip-log entries emitted by
 * `GroupWildcardRulesToEachRector` at decision-point bails. Each
 * `skip_<reason>.php.inc` fixture under `Fixture/` exercises a
 * specific bail; this test runs the rector with verbose mode enabled
 * and asserts the expected reason string lands in the skip log.
 *
 * Pinning the reason strings here means a future refactor that
 * silently drops or renames a skip message produces a test failure
 * rather than a silent regression.
 */
final class GroupWildcardSkipLogTest extends AbstractRectorTestCase
{
    private ?string $originalVerbose = null;

    private string $originalCwd = '';

    private string $tempCwd = '';

    protected function setUp(): void
    {
        parent::setUp();

        $env = getenv(Diagnostics::VERBOSE_ENV);
        $this->originalVerbose = $env === false ? null : $env;
        putenv(Diagnostics::VERBOSE_ENV . '=1');

        // Switch cwd to a per-test tempdir so the verbose-mode skip log
        // (which Diagnostics derives from cwd) lands inside an isolated
        // dir. Without this, parallel test workers (paratest) targeting
        // the same repo cwd would race on the same log file + sentinel,
        // producing flaky reads. Per-test cwd guarantees per-process,
        // per-test isolation.
        $this->originalCwd = getcwd() ?: sys_get_temp_dir();
        $this->tempCwd = sys_get_temp_dir() . '/group-wildcard-skiplog-' . uniqid('', true);

        if (! mkdir($this->tempCwd) && ! is_dir($this->tempCwd)) {
            $this->fail('Failed to create per-test cwd');
        }

        chdir($this->tempCwd);

        $this->resetSkipLogState();
        $this->unlinkSkipLog();
    }

    protected function tearDown(): void
    {
        $this->unlinkSkipLog();
        $this->resetSkipLogState();

        if ($this->originalVerbose === null) {
            putenv(Diagnostics::VERBOSE_ENV);
            unset($_SERVER[Diagnostics::VERBOSE_ENV], $_ENV[Diagnostics::VERBOSE_ENV]);
        } else {
            putenv(Diagnostics::VERBOSE_ENV . '=' . $this->originalVerbose);
        }

        if ($this->originalCwd !== '') {
            chdir($this->originalCwd);
        }

        if ($this->tempCwd !== '' && is_dir($this->tempCwd)) {
            @rmdir($this->tempCwd);
        }

        parent::tearDown();
    }

    #[DataProvider('skipFixtures')]
    public function testFixtureEmitsExpectedSkipReason(string $fixturePath, string $expectedReasonSubstring): void
    {
        // Copy fixture to a unique temp path so Rector's per-process file
        // cache (path+mtime keyed) doesn't hit a stale entry from
        // GroupWildcardRulesToEachRectorTest having already processed
        // the canonical fixture path in the same suite run.
        $tempFixture = $this->copyFixtureToUniquePath($fixturePath);

        try {
            $this->doTestFile($tempFixture);
        } finally {
            @unlink($tempFixture);
        }

        $logPath = Diagnostics::skipLogPath();
        $this->assertFileExists($logPath, sprintf(
            'Expected skip log at %s after processing %s',
            $logPath,
            basename($fixturePath),
        ));

        $contents = (string) file_get_contents($logPath);

        $this->assertStringContainsString(
            $expectedReasonSubstring,
            $contents,
            sprintf(
                "Fixture '%s' did not emit expected skip reason.\nLog contents:\n%s",
                basename($fixturePath),
                $contents,
            ),
        );
    }

    private function copyFixtureToUniquePath(string $sourcePath): string
    {
        // Use sys_get_temp_dir() rather than dirname($sourcePath) so a
        // crash mid-test doesn't leave `__skiplog_*` files in the
        // Fixture/ directory, where they would be picked up by the
        // sibling AbstractRectorTestCase's `yieldFilesFromDirectory()`
        // provider on the next run and fail with a no-such-fixture
        // contract mismatch.
        $contents = (string) file_get_contents($sourcePath);
        $unique = uniqid('', true);
        $destination = sys_get_temp_dir() . '/skiplog_' . $unique . '_' . basename($sourcePath);
        file_put_contents($destination, $contents);

        return $destination;
    }

    /**
     * @return Iterator<string, array{0: string, 1: string}>
     */
    public static function skipFixtures(): Iterator
    {
        $base = __DIR__ . '/Fixture/';

        yield 'double wildcard' => [
            $base . 'skip_double_wildcard.php.inc',
            "double wildcard or non-first '*' in key suffix",
        ];

        yield 'non-redundant wildcard parent' => [
            $base . 'skip_non_redundant_wildcard.php.inc',
            "wildcard parent 'tags.*' has type-specific rules that would be lost in grouping",
        ];

        yield 'raw array parent (group has non-FluentRule entries)' => [
            $base . 'skip_raw_array_parent.php.inc',
            "wildcard group has non-FluentRule entries — cannot fold to each() (parent: 'interactions')",
        ];

        yield 'wrong parent factory type' => [
            $base . 'skip_wrong_parent_type.php.inc',
            "parent factory string() doesn't support each()/children()",
        ];

        yield 'complex concat key' => [
            $base . 'skip_complex_concat_key.php.inc',
            'concat key too complex to parse for grouping',
        ];

        yield 'wildcard-prefix concat (Phase 1 — parser recognized, fold deferred to Phase 2)' => [
            $base . 'skip_wildcard_prefix_concat_phase_1.php.inc',
            'wildcard-prefix concat key not yet folded',
        ];
    }

    public function provideConfigFilePath(): string
    {
        return __DIR__ . '/config/configured_grouping_rule.php';
    }

    private function unlinkSkipLog(): void
    {
        foreach (Diagnostics::allSkipLogArtifacts() as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    private function resetSkipLogState(): void
    {
        $reflection = new ReflectionClass(GroupWildcardRulesToEachRector::class);
        $traitNames = array_map(
            static fn (object $t): string => $t::class,
            $reflection->getTraits(),
        );

        if (! in_array(LogsSkipReasons::class, $traitNames, true)) {
            return;
        }

        foreach (['loggedSkips', 'logSessionVerified'] as $propName) {
            if ($reflection->hasProperty($propName)) {
                $prop = $reflection->getProperty($propName);
                $prop->setValue(null, $propName === 'loggedSkips' ? [] : false);
            }
        }
    }
}

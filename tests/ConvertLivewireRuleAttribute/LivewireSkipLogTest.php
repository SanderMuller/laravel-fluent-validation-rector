<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute;

use Iterator;
use PHPUnit\Framework\Attributes\DataProvider;
use Rector\Testing\PHPUnit\AbstractRectorTestCase;
use ReflectionClass;
use SanderMuller\FluentValidationRector\Internal\Diagnostics;
use SanderMuller\FluentValidationRector\Rector\Concerns\LogsSkipReasons;
use SanderMuller\FluentValidationRector\Rector\ConvertLivewireRuleAttributeRector;

/**
 * Regression coverage for the skip-log entries emitted by
 * `ConvertLivewireRuleAttributeRector` at decision-point bails. Each
 * `skip_<reason>.php.inc` fixture under `Fixture/` exercises a
 * specific bail; this test runs the rector with verbose mode enabled
 * and asserts the expected reason string lands in the skip log.
 *
 * Currently pins the 1.2.0 layer-2 compose-conflict warning emitted
 * when a Livewire class with property-level `#[Rule]` / `#[Validate]`
 * attribute also uses `HasFluentValidation` (directly or via
 * ancestor). Mirrors the established pattern in
 * `tests/GroupWildcardRulesToEach/GroupWildcardSkipLogTest.php`,
 * `tests/ValidationStringToFluentRule/StringConverterSkipLogTest.php`,
 * and `tests/ValidationArrayToFluentRule/ArrayConverterSkipLogTest.php`.
 *
 * Pinning the reason strings here means a future refactor that
 * silently drops or renames a skip message produces a test failure
 * rather than a silent regression — which would otherwise be invisible
 * because the source-diff fixture format only catches source-mutation
 * regressions, not skip-log emit regressions.
 */
final class LivewireSkipLogTest extends AbstractRectorTestCase
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

        $this->originalCwd = getcwd() ?: sys_get_temp_dir();
        $this->tempCwd = sys_get_temp_dir() . '/livewire-skiplog-' . uniqid('', true);

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

    /**
     * 1.2.1 Codex-review guard: the per-property overlap-bail emit must
     * NOT report a non-convertible empty-marker / named-arg-only attr
     * as if `KEY_OVERLAP_BEHAVIOR=partial` would convert it. The fixture
     * has one convertible-overlapping property (`$name`) and one empty-
     * marker property (`$bio`); the bail-emit must surface the
     * `$name` overlap entry but stay silent on `$bio`.
     */
    public function testEmptyMarkerPropertyIsNotMisreported(): void
    {
        $fixturePath = __DIR__ . '/Fixture/skip_overlap_bail_empty_marker_silent.php.inc';
        $tempFixture = $this->copyFixtureToUniquePath($fixturePath);

        try {
            $this->doTestFile($tempFixture);
        } finally {
            @unlink($tempFixture);
        }

        $contents = (string) file_get_contents(Diagnostics::skipLogPath());

        $this->assertStringContainsString(
            "property `\$name` Livewire attribute key(s) ['name'] overlap explicit",
            $contents,
            'Convertible overlapping property `$name` must still emit the per-property overlap entry.',
        );

        $this->assertStringNotContainsString(
            '`$bio`',
            $contents,
            'Empty-marker `#[Validate]` attr on `$bio` is non-convertible; the per-property '
            . 'overlap-bail emit must NOT surface it (codex review on the 1.2.1 candidate caught '
            . 'the original predict-keys path fabricating the property name as a key for argless '
            . 'attrs and the bail-emit then misdirecting the user to flip KEY_OVERLAP_BEHAVIOR=partial).',
        );
    }

    private function copyFixtureToUniquePath(string $sourcePath): string
    {
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

        // 1.2.0 layer-2 compose-conflict warning. The bail covers ONLY
        // the inherited shape — direct-trait use on the class itself
        // converts cleanly (rector merges the attribute into a local
        // `rules()` method, or installs one), so it is intentionally
        // not flagged here. See `warnLivewireFluentTraitComposeConflict`
        // PHPDoc for the narrowing rationale (Codex review on the
        // 1.2.0 candidate).
        yield 'HasFluentValidation trait + Livewire #[Rule] (inherited)' => [
            $base . 'skip_livewire_attribute_with_fluent_trait_inherited.php.inc',
            'property `$phoneNumber` carries `#[Livewire\\Attributes\\Rule]` (or `#[Validate]`) but an ancestor class uses `HasFluentValidation` trait — the attribute is silently ignored at runtime',
        ];

        // 1.2.1 per-property overlap-bail emit — replaces the pre-1.2.1
        // single classwide skip with one entry per Livewire-attributed
        // property, naming whether its predicted emit key(s) overlap
        // explicit `$this->validate([...])` keys. The
        // `skip_hybrid_validateOnly_with_rules.php.inc` fixture has two
        // `#[Validate]` attrs + an explicit `validateOnly('name', ['name'
        // => ...])` call — `$name` overlaps, `$email` doesn't. Both
        // dataset entries below assert the verbatim pinpoint message
        // for each property.
        yield 'overlap-bail per-property emit (overlapping property)' => [
            $base . 'skip_hybrid_validateOnly_with_rules.php.inc',
            "property `\$name` Livewire attribute key(s) ['name'] overlap explicit `\$this->validate(...)` key(s) ['name'] — KEY_OVERLAP_BEHAVIOR=bail (default) keeps it as attrs",
        ];

        yield 'overlap-bail per-property emit (non-overlapping property)' => [
            $base . 'skip_hybrid_validateOnly_with_rules.php.inc',
            "property `\$email` Livewire attribute key(s) ['email'] do NOT overlap explicit `\$this->validate(...)` key(s) ['name'] — KEY_OVERLAP_BEHAVIOR=bail (default) skips classwide regardless. Switch to KEY_OVERLAP_BEHAVIOR=partial to release this property from the classwide bail (whether the attribute itself converts depends on its shape — separate per-attr skip-log entries flag non-convertible attrs)",
        ];
    }

    public function provideConfigFilePath(): string
    {
        return __DIR__ . '/config/configured_livewire_attribute_rule.php';
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
        $reflection = new ReflectionClass(ConvertLivewireRuleAttributeRector::class);
        $traitNames = array_map(
            static fn (object $t): string => $t::class,
            $reflection->getTraits(),
        );

        if (! in_array(LogsSkipReasons::class, $traitNames, true)) {
            $directTraitsHaveLogs = array_any(
                $reflection->getTraitNames(),
                fn (string $name): bool => self::traitGraphIncludes($name, LogsSkipReasons::class),
            );

            if (! $directTraitsHaveLogs) {
                return;
            }
        }

        foreach (['loggedSkips', 'logSessionVerified', 'composeConflictWarnings'] as $propName) {
            if ($reflection->hasProperty($propName)) {
                $prop = $reflection->getProperty($propName);
                $default = $propName === 'logSessionVerified' ? false : [];
                $prop->setValue(null, $default);
            }
        }
    }

    private static function traitGraphIncludes(string $traitName, string $target): bool
    {
        if ($traitName === $target) {
            return true;
        }

        if (! trait_exists($traitName)) {
            return false;
        }

        $reflection = new ReflectionClass($traitName);

        foreach ($reflection->getTraitNames() as $usedTrait) {
            if (self::traitGraphIncludes($usedTrait, $target)) {
                return true;
            }
        }

        return false;
    }
}

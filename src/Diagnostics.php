<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector;

use Composer\InstalledVersions;
use OutOfBoundsException;

/**
 * Centralises the on/off gate for the skip-log diagnostic sink and resolves
 * the file path the log should live at given the current verbosity.
 *
 * Verbose mode is off by default as of 0.5.0. When off, skip entries still
 * need to be counted so the end-of-run summary can hint at their existence,
 * but the log file must not appear in the consumer's project root — CI
 * auto-fix workflows were picking the file up as a dirty artifact and
 * attempting to commit+push it (blocked on protected branches, broke
 * mijntp's pipeline). Off-mode writes go to a cwd-hash-scoped path under
 * `sys_get_temp_dir()` so they never surface in the repo. The RunSummary
 * unlinks the off-mode file after emitting its hint line.
 *
 * Enable by exporting the env var before invoking Rector:
 *
 *   FLUENT_VALIDATION_RECTOR_VERBOSE=1 vendor/bin/rector process --clear-cache
 *
 * Env-only is deliberate. A `Diagnostics::enable()` helper that called
 * `putenv()` would be more ergonomic but has two issues: (1) `putenv()` is
 * disallowed by this package's static analysis (overwrite hazard for the
 * process-wide env vector), and (2) the flag needs to propagate to
 * parallel workers, which are fresh PHP processes spawned via `proc_open`
 * that inherit OS-level env — setting `$_SERVER`/`$_ENV` in the parent
 * doesn't cross the process boundary. Shell-exported env inherits to
 * workers automatically; in-process mutation does not.
 *
 * @internal
 */
final class Diagnostics
{
    public const VERBOSE_ENV = 'FLUENT_VALIDATION_RECTOR_VERBOSE';

    /**
     * Filename written under the verbose log directory. Kept stable
     * across the .cache/ relocation so consumers grepping for the file
     * still find it.
     */
    public const VERBOSE_LOG_FILENAME = 'rector-fluent-validation-skips.log';

    /**
     * Subdirectory under the project root where the verbose log lands.
     * Most projects already gitignore `.cache/` (Rector itself
     * recommends it as the default cache directory), so writing the log
     * there avoids the gitignore footgun GH #3 reported. Auto-created
     * by `verboseLogPath()` if missing; falls back to cwd root if the
     * subdirectory can't be created (read-only mount, etc.).
     */
    public const VERBOSE_LOG_DIR = '.cache';

    /**
     * Legacy filename — used by `allSkipLogArtifacts()` so the cleanup
     * sweep still removes pre-0.14.1 cwd-root logs that consumers may
     * have inherited. Not written to by anything in 0.14.1+.
     */
    public const LEGACY_VERBOSE_LOG_FILENAME = '.rector-fluent-validation-skips.log';

    /**
     * Default tier — only always-on (non-`verboseOnly`) entries surface.
     */
    public const TIER_OFF = 'off';

    /**
     * Middle tier — surfaces `verboseOnly` entries flagged `actionable: true`
     * in addition to the always-on set. Skips non-actionable noise
     * (trait-already-present, Livewire-detected-not-FormRequest, etc.).
     */
    public const TIER_ACTIONABLE = 'actionable';

    /**
     * Legacy "everything" tier. `VERBOSE_ENV=1` / `=true` / `=all` all
     * resolve here. Preserves pre-0.13 behaviour exactly — every
     * `verboseOnly` entry writes regardless of actionable flag.
     */
    public const TIER_ALL = 'all';

    /**
     * OS env only. `$_SERVER` / `$_ENV` are intentionally not consulted:
     * they can diverge from the process environment (e.g. Dotenv/bootstrap
     * code mutates them but not OS env), which would produce a
     * parent-vs-worker inconsistency — Rector's parallel workers are
     * `proc_open`-spawned fresh PHP processes and inherit only OS env, not
     * userland superglobals. Parent seeing verbose-on while workers see
     * verbose-off would split skip writes between the cwd log and the
     * tmp log, losing both the file and the end-of-run hint.
     *
     * Kept for back-compat: returns true iff the resolved tier is `all`.
     * Internal call sites that need to distinguish `actionable` from `all`
     * should call {@see verbosityTier()} directly.
     */
    public static function isVerbose(): bool
    {
        return self::verbosityTier() === self::TIER_ALL;
    }

    /**
     * Resolve the 3-level verbosity tier from the OS env. Accepted values:
     *
     *   - unset / empty                         → `off`
     *   - `actionable`                          → `actionable`
     *   - `1` / `true` / `all` / any other non-empty truthy → `all`
     *
     * Parsing is case-insensitive for the named tier values. Anything
     * non-empty that isn't `actionable` falls through to `all` so legacy
     * `VERBOSE=1` consumers keep their existing everything-output behaviour.
     */
    public static function verbosityTier(): string
    {
        $raw = getenv(self::VERBOSE_ENV);

        if ($raw === false || $raw === '') {
            return self::TIER_OFF;
        }

        $normalized = strtolower($raw);

        if ($normalized === self::TIER_ACTIONABLE) {
            return self::TIER_ACTIONABLE;
        }

        return self::TIER_ALL;
    }

    /**
     * Verbose mode's path: `<cwd>/.cache/<filename>`. Auto-creates the
     * `.cache/` subdir if missing; falls back to cwd root if the
     * subdir can't be created (read-only mount, restrictive perms).
     * Exposed independently of `isVerbose()` so cleanup code can always
     * target it (upgrade scenarios + mode toggles leave stale files
     * that the current-mode `skipLogPath()` would skip).
     */
    public static function verboseLogPath(): string
    {
        $cwd = getcwd();
        $base = $cwd === false ? sys_get_temp_dir() : $cwd;
        $cacheDir = $base . '/' . self::VERBOSE_LOG_DIR;

        if (! is_dir($cacheDir) && ! @mkdir($cacheDir, 0755, true) && ! is_dir($cacheDir)) {
            // Fall back to cwd root if .cache/ can't be created.
            return $base . '/' . self::VERBOSE_LOG_FILENAME;
        }

        return $cacheDir . '/' . self::VERBOSE_LOG_FILENAME;
    }

    /**
     * Path-relative-to-cwd label used in user-facing summary lines.
     * Falls back to the bare filename when the resolved log path
     * doesn't sit under cwd (off-mode tmp path, read-only-cwd fallback).
     */
    public static function verboseLogDisplayPath(): string
    {
        $cwd = getcwd();
        $logPath = self::verboseLogPath();

        if ($cwd !== false && str_starts_with($logPath, $cwd . '/')) {
            return substr($logPath, strlen($cwd) + 1);
        }

        return self::VERBOSE_LOG_FILENAME;
    }

    /**
     * Off-mode path: sys_get_temp_dir scoped by a short cwd hash so
     * concurrent Rector runs in different project roots don't collide and
     * the RunSummary reader can locate this run's file without knowing the
     * producing workers' PIDs.
     */
    public static function quietLogPath(): string
    {
        $cwd = getcwd();
        $key = $cwd === false ? 'unknown' : substr(hash('sha256', $cwd), 0, 12);

        return sys_get_temp_dir() . '/rector-fluent-validation-skips-' . $key . '.log';
    }

    public static function skipLogPath(): string
    {
        // Any opt-in tier (`actionable` or `all`) surfaces the log in cwd —
        // consumers who asked for diagnostic output want it inspectable in
        // the project root. Only `off` (no opt-in) hides the log in tmp.
        return self::verbosityTier() === self::TIER_OFF
            ? self::quietLogPath()
            : self::verboseLogPath();
    }

    public static function skipLogSentinelPath(): string
    {
        return self::skipLogPath() . '.session';
    }

    /**
     * Every path the package may have written at any point in its history
     * or across mode toggles: current verbose path (.cache/), legacy
     * verbose path (cwd-root, pre-0.14.1), off-mode tmp path, and each
     * file's `.session` sentinel. Used by `RunSummary::unlinkLogArtifacts()`
     * so a single parent-side cleanup sweep clears upgrade detritus
     * (legacy 0.4.x cwd-root log) AND post-0.14.1 .cache/ files AND
     * mode-toggle residue (old off-mode file in tmp when user flips
     * to verbose, or vice versa).
     *
     * @return list<string>
     */
    public static function allSkipLogArtifacts(): array
    {
        $cwd = getcwd();
        $base = $cwd === false ? sys_get_temp_dir() : $cwd;
        $legacy = $base . '/' . self::LEGACY_VERBOSE_LOG_FILENAME;

        return [
            self::verboseLogPath(),
            self::verboseLogPath() . '.session',
            $legacy,
            $legacy . '.session',
            self::quietLogPath(),
            self::quietLogPath() . '.session',
        ];
    }

    /**
     * Multi-line header written at the top of the verbose log on
     * truncation (per-run reset). Includes the package version, ISO-8601
     * UTC timestamp, and the resolved verbose tier. GH #4 + cross-version
     * triage signal: lets a downstream consumer's CI diff identify which
     * release produced a given log shape.
     *
     * The package version is read from the package's own composer.json
     * via `__DIR__/../composer.json`. Falls back to "unknown" if the
     * file is unreadable (rare — package's own composer.json should
     * always be present in the installed tree).
     */
    public static function skipLogHeader(): string
    {
        $version = self::packageVersion();
        $timestamp = gmdate('Y-m-d\TH:i:s\Z');
        $tier = self::verbosityTier();

        return sprintf(
            "# laravel-fluent-validation-rector %s — generated %s\n# verbose tier: %s\n\n",
            $version,
            $timestamp,
            $tier,
        );
    }

    /**
     * Best-effort read of the installed package version. Composer's
     * `InstalledVersions` is populated at install time from the
     * resolved git tags / dev-branch refs, so it gives the actual
     * shipped version even when composer.json itself doesn't declare
     * a `version` field (the recommended Composer pattern). Falls back
     * to "unknown" if Composer's runtime API isn't available (rare —
     * shipped with Composer 2+) or if this package isn't registered
     * (impossible for an installed dep, possible during local
     * development before `composer install`).
     */
    private static function packageVersion(): string
    {
        if (! class_exists(InstalledVersions::class)) {
            return 'unknown';
        }

        try {
            $version = InstalledVersions::getPrettyVersion('sandermuller/laravel-fluent-validation-rector');
        } catch (OutOfBoundsException) {
            return 'unknown';
        }

        return is_string($version) ? $version : 'unknown';
    }
}

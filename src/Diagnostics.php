<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector;

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
 */
final class Diagnostics
{
    public const VERBOSE_ENV = 'FLUENT_VALIDATION_RECTOR_VERBOSE';

    public const VERBOSE_LOG_FILENAME = '.rector-fluent-validation-skips.log';

    /**
     * OS env only. `$_SERVER` / `$_ENV` are intentionally not consulted:
     * they can diverge from the process environment (e.g. Dotenv/bootstrap
     * code mutates them but not OS env), which would produce a
     * parent-vs-worker inconsistency — Rector's parallel workers are
     * `proc_open`-spawned fresh PHP processes and inherit only OS env, not
     * userland superglobals. Parent seeing verbose-on while workers see
     * verbose-off would split skip writes between the cwd log and the
     * tmp log, losing both the file and the end-of-run hint.
     */
    public static function isVerbose(): bool
    {
        return getenv(self::VERBOSE_ENV) === '1';
    }

    /**
     * Verbose mode's path: cwd. Exposed independently of
     * `isVerbose()` so cleanup code can always target it (upgrade
     * scenarios + mode toggles leave stale cwd files that the current-mode
     * `skipLogPath()` would skip).
     */
    public static function verboseLogPath(): string
    {
        $cwd = getcwd();
        $base = $cwd === false ? sys_get_temp_dir() : $cwd;

        return $base . '/' . self::VERBOSE_LOG_FILENAME;
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
        return self::isVerbose() ? self::verboseLogPath() : self::quietLogPath();
    }

    public static function skipLogSentinelPath(): string
    {
        return self::skipLogPath() . '.session';
    }

    /**
     * Every path the package may have written at any point in its history
     * or across mode toggles: both verbose and off-mode logs + their
     * sentinels. Used by `RunSummary::unlinkLogArtifacts()` so a single
     * parent-side cleanup sweep clears both upgrade detritus (0.4.x verbose
     * log in cwd) and mode-toggle residue (old off-mode file in tmp when
     * user flips to verbose, or vice versa).
     *
     * @return list<string>
     */
    public static function allSkipLogArtifacts(): array
    {
        return [
            self::verboseLogPath(),
            self::verboseLogPath() . '.session',
            self::quietLogPath(),
            self::quietLogPath() . '.session',
        ];
    }
}

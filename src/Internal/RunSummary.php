<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Internal;

/**
 * Emits a single STDOUT line at the end of a Rector run reporting the
 * number of skip entries from `.rector-fluent-validation-skips.log`. Users
 * running rector against a codebase with heavy trait-hoisting (abstract
 * base classes propagating the performance traits) or hybrid Livewire
 * bail conditions see `[OK] 0 files changed` from Rector itself and
 * assume the rules didn't fire. The skip log has the actual story, but
 * it's invisible until someone tells you to look. One line surfaces it.
 *
 * Two output shapes depending on verbose mode (see Diagnostics):
 *   - Verbose on: log is in cwd, line references the file path.
 *   - Verbose off (default): log is in sys_get_temp_dir, invisible to the
 *     consumer. Line reports the skip count and hints at the re-run
 *     command to get the details. The tmp file is unlinked in the
 *     shutdown closure after emit so no artifact persists across runs.
 *
 * Registration happens from `config/config.php`, loaded by
 * rector-extension-installer in consumer projects. The shutdown function
 * is gated on "am I the parent process?" via absence of Rector's
 * `--identifier` CLI arg — workers are spawned with `--identifier <uuid>`,
 * the parent is not. Parent-process emit sidesteps the `withParallel()`
 * STDERR-swallow problem that motivated the file sink in 0.4.2.
 *
 * @internal
 */
final class RunSummary
{
    private static bool $registered = false;

    /**
     * Register the shutdown emit exactly once per PHP process. Idempotent
     * across repeated config-file loads.
     */
    public static function registerShutdownHandler(): void
    {
        if (self::$registered) {
            return;
        }

        self::$registered = true;

        if (! self::isRectorInvocation()) {
            // Rule constructors fire whenever the class is instantiated —
            // including inside consumer test suites that happen to spin up
            // our rector classes for their own reasons. We only want the
            // summary to emit during actual `vendor/bin/rector process`
            // runs, not during arbitrary PHP processes that touched us.
            return;
        }

        if (self::isWorkerProcess()) {
            return;
        }

        // Clear stale log + sentinel from a prior abnormally-terminated run.
        // Normal truncation happens inside workers via sentinel-coordinated
        // `ensureLogSessionFreshness`, but that path only fires when a
        // worker actually calls `logSkip`. A zero-skip run leaves any prior
        // run's file intact, which would make `format()` emit a phantom
        // hint ("N skips" for entries from the last run, not this one).
        // Parent-side cleanup runs before workers spawn, so there's no
        // race. If this run produces skips, the first worker recreates
        // both files via the normal sentinel path.
        self::unlinkLogArtifacts();

        register_shutdown_function(static function (): void {
            // Always-emit on zero-entry runs (P2 #9 / GH #4 follow-up):
            // when verbose tier is on AND no worker wrote to the log,
            // create the file with the header so CI can diff it across
            // runs without seeing a "file missing → file present"
            // transition the moment a single skip fires. Off-mode
            // (TIER_OFF) skips this — the temp-mode log is intended to
            // be invisible to the consumer.
            if (Diagnostics::verbosityTier() !== Diagnostics::TIER_OFF
                && ! is_file(Diagnostics::skipLogPath())) {
                @file_put_contents(Diagnostics::skipLogPath(), Diagnostics::skipLogHeader(), LOCK_EX);
            }

            $line = self::format();

            if ($line === null) {
                return;
            }

            fwrite(STDOUT, $line);

            // Off-mode: cleanup runs after emit so no artifact survives
            // into the next run. Any opt-in tier (`actionable` / `all`)
            // leaves the log in cwd for the user to inspect — the
            // consumer asked for it.
            if (Diagnostics::verbosityTier() === Diagnostics::TIER_OFF) {
                self::unlinkLogArtifacts();
            }
        });
    }

    /**
     * Remove every skip-log artifact the package may have produced across
     * history or mode toggles — both verbose (cwd) and off-mode (/tmp)
     * paths. Sweeping both modes means a fresh default-mode run still
     * clears the legacy `.rector-fluent-validation-skips.log` a consumer
     * inherited from 0.4.x or from a prior verbose-mode invocation, which
     * is the whole point of 0.5.0's "zero cwd artifacts" promise.
     *
     * Internal helper, shared by parent-init cleanup and off-mode
     * post-emit cleanup. Public only for unit testing.
     */
    public static function unlinkLogArtifacts(): void
    {
        foreach (Diagnostics::allSkipLogArtifacts() as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    /**
     * Build the summary line from the current-mode skip log path (see
     * `Diagnostics::skipLogPath()`). Returns null when the log is absent
     * or empty. Pure read — no side effects; cleanup is the shutdown
     * closure's responsibility. Exposed publicly for unit-testing without
     * having to trigger a full PHP shutdown cycle.
     */
    public static function format(): ?string
    {
        $logPath = Diagnostics::skipLogPath();

        if (! is_file($logPath)) {
            return null;
        }

        $size = @filesize($logPath);

        if ($size === false || $size === 0) {
            return null;
        }

        $contents = @file_get_contents($logPath);

        if ($contents === false) {
            return null;
        }

        // Count entry lines specifically — header lines start with `# `
        // and are excluded by the `[fluent-validation:skip]` prefix
        // match. Pre-0.14.1 the file was entries-only and `substr_count
        // newline` worked; with the new header that count would be
        // inflated by the header lines.
        $count = substr_count($contents, '[fluent-validation:skip]');

        if ($count === 0) {
            return null;
        }

        $noun = $count === 1 ? 'entry' : 'entries';

        if (Diagnostics::verbosityTier() !== Diagnostics::TIER_OFF) {
            // At TIER_ALL the firehose includes ~100s of non-actionable
            // informational entries (trait-already-present, parent-inherits-
            // trait, Livewire-detected). Consumers running with `=1` (legacy
            // alias for `=all`) often want the actionable subset instead;
            // surface the filter knob in-band so they don't have to re-discover
            // it from PUBLIC_API.md. Collectiq 1.2.0 dogfeed (2026-04-29)
            // measured 110 entries at TIER_ALL vs. 5 at TIER_ACTIONABLE on the
            // same surface — the tip cuts the firehose ~22× when applied.
            $tip = Diagnostics::verbosityTier() === Diagnostics::TIER_ALL
                ? sprintf(' (tip: %s=actionable filters informational entries)', Diagnostics::VERBOSE_ENV)
                : '';

            return sprintf(
                "\n[fluent-validation] %d skip %s written to %s — see for details%s\n",
                $count,
                $noun,
                Diagnostics::verboseLogDisplayPath(),
                $tip,
            );
        }

        // `--clear-cache` matters: bail results are cached per file, so a
        // plain re-run with verbose env set still produces an empty log on
        // cached files. The hint has to be actionable as-copied.
        // `=actionable` is the recommended entry point — surfaces only
        // entries a consumer can act on; `=1` (legacy) / `=all` remain
        // available for complete diagnostic output.
        return sprintf(
            "\n[fluent-validation] %d skip %s. Re-run with %s=actionable and --clear-cache for details.\n",
            $count,
            $noun,
            Diagnostics::VERBOSE_ENV,
        );
    }

    /**
     * Returns true when the current process should register the shutdown
     * emit — i.e. this is the parent process of an actual `vendor/bin/rector`
     * invocation, not a worker, not a test suite, not a composer script.
     * Exposed publicly for unit testing against stubbed argv.
     *
     * @param  list<string>  $argv
     */
    public static function shouldRegister(array $argv): bool
    {
        return self::isRectorInvocationFromArgv($argv)
            && ! self::isWorkerProcessFromArgv($argv);
    }

    /**
     * Workers are spawned by Rector's parallel executor with `--identifier <uuid>`
     * appended to their CLI args. The parent process doesn't have this flag.
     * Gate the shutdown emit on parent-ness so each worker doesn't independently
     * emit its own summary line.
     */
    private static function isWorkerProcess(): bool
    {
        /** @var list<string>|null $argv */
        $argv = $_SERVER['argv'] ?? null;

        if (! is_array($argv)) {
            return false;
        }

        return self::isWorkerProcessFromArgv($argv);
    }

    /**
     * @param  list<string>  $argv
     */
    private static function isWorkerProcessFromArgv(array $argv): bool
    {
        return in_array('--identifier', $argv, true);
    }

    /**
     * Detects whether the current PHP process is a `vendor/bin/rector`
     * invocation (parent or worker). When rule constructors call
     * `registerShutdownHandler()`, they may fire in consumer test suites,
     * composer post-install scripts, IDE inspection runs — anywhere the
     * rule class happens to be autoloaded and instantiated. Emitting the
     * summary in those contexts would be noise; we only want it during
     * actual Rector runs.
     *
     * Heuristic: `$_SERVER['argv'][0]` basename contains `rector`. Matches
     * `rector`, `rector.phar`, `vendor/bin/rector`, the rebuilt-binary name
     * various CI setups use, and the `rector` substring in worker argv[0]
     * when Rector re-invokes itself. Doesn't match `pest`, `phpunit`,
     * `phpstan`, `php`, `composer`, etc.
     */
    private static function isRectorInvocation(): bool
    {
        /** @var list<string>|null $argv */
        $argv = $_SERVER['argv'] ?? null;

        if (! is_array($argv)) {
            return false;
        }

        return self::isRectorInvocationFromArgv($argv);
    }

    /**
     * @param  list<string>  $argv
     */
    private static function isRectorInvocationFromArgv(array $argv): bool
    {
        if (! isset($argv[0])) {
            return false;
        }

        return str_contains(basename($argv[0]), 'rector');
    }
}

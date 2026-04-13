<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector;

/**
 * Emits a single STDOUT line at the end of a Rector run pointing users at
 * `.rector-fluent-validation-skips.log` when it contains entries. Users
 * running rector against a codebase with heavy trait-hoisting (abstract
 * base classes propagating the performance traits) or hybrid Livewire
 * bail conditions see `[OK] 0 files changed` from Rector itself and
 * assume the rules didn't fire. The skip log has the actual story, but
 * it's invisible until someone tells you to look. One line surfaces it.
 *
 * Registration happens from `config/config.php`, loaded by
 * rector-extension-installer in consumer projects. The shutdown function
 * is gated on "am I the parent process?" via absence of Rector's
 * `--identifier` CLI arg — workers are spawned with `--identifier <uuid>`,
 * the parent is not. Parent-process emit sidesteps the `withParallel()`
 * STDERR-swallow problem that motivated the file sink in 0.4.2.
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

        register_shutdown_function(static function (): void {
            $line = self::format();

            if ($line !== null) {
                fwrite(STDOUT, $line);
            }
        });
    }

    /**
     * Build the summary line based on the skip log in the current working
     * directory. Returns null when the log is absent or empty. Exposed
     * publicly for unit-testing without having to trigger a full PHP
     * shutdown cycle.
     */
    public static function format(): ?string
    {
        $cwd = getcwd();

        if ($cwd === false) {
            return null;
        }

        $logPath = $cwd . '/.rector-fluent-validation-skips.log';

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

        $count = substr_count($contents, "\n");

        if ($count === 0) {
            return null;
        }

        return sprintf(
            "\n[fluent-validation] %d skip %s written to .rector-fluent-validation-skips.log — see for details\n",
            $count,
            $count === 1 ? 'entry' : 'entries',
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

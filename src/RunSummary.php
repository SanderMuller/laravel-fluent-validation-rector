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

        return in_array('--identifier', $argv, true);
    }
}

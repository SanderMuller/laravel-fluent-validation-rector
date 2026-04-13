<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector\Concerns;

use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Class_;
use Rector\Rector\AbstractRector;
use ReflectionClass;

/**
 * Writes `[fluent-validation:skip]` entries to
 * `.rector-fluent-validation-skips.log` in the current working directory,
 * and (when `FLUENT_VALIDATION_RECTOR_VERBOSE=1` is set) also mirrors them
 * to STDERR for local, single-process Rector runs.
 *
 * Purpose: give users visibility into why a given class wasn't converted
 * without requiring them to source-dive into the rule. Only called at
 * meaningful decision points (abstract, Livewire, already-has-trait,
 * hybrid-validate-call, etc.) — not at every early-exit node-type mismatch,
 * which would be too noisy.
 *
 * The file sink exists because Rector's parallel executor
 * (`->withParallel(...)` — the default in projects scaffolded by
 * `rector init`) doesn't forward child-worker STDERR to the parent's
 * STDERR stream. Worker `fwrite(STDERR, …)` calls are effectively
 * `/dev/null` for consumers running Rector in parallel mode, which
 * currently is the overwhelming majority of real-world usage. File
 * writes from workers persist, so append-with-lock keeps concurrent
 * worker output safe and produces a single inspectable log file after
 * the Rector run finishes.
 *
 * The log lives in the consumer's current working directory (typically
 * the project root). Add `.rector-fluent-validation-skips.log` to
 * `.gitignore` if you don't want it tracked. The file is overwritten at
 * the start of each Rector invocation so stale entries from previous
 * runs don't leak in.
 *
 * Each line is written exactly once per (rule, file, class, reason)
 * tuple to deduplicate multi-pass output within a single Rector run.
 *
 * @phpstan-require-extends AbstractRector
 */
trait LogsSkipReasons
{
    /**
     * De-dup set, scoped per-process.
     *
     * @var array<string, true>
     */
    private static array $loggedSkips = [];

    /**
     * Tracks whether the log file has been truncated in this PID. We
     * truncate on first write so stale entries from a prior Rector run
     * don't leak into the current run's output, but only once per
     * process so subsequent workers append instead of truncating each
     * other's entries.
     */
    private static bool $logFileTruncated = false;

    private function logSkip(Class_ $class, string $reason): void
    {
        $file = $this->getFile()->getFilePath();
        $className = $class->namespacedName?->toString()
            ?? ($class->name instanceof Identifier ? $class->name->toString() : 'anonymous');
        $rule = (new ReflectionClass($this))->getShortName();

        $key = $rule . '|' . $file . '|' . $className . '|' . $reason;

        if (isset(self::$loggedSkips[$key])) {
            return;
        }

        self::$loggedSkips[$key] = true;

        $entry = sprintf("[fluent-validation:skip] %s %s (%s): %s\n", $rule, $className, $file, $reason);

        $logFilePath = self::skipLogFilePath();
        $flags = self::$logFileTruncated ? FILE_APPEND | LOCK_EX : LOCK_EX;
        @file_put_contents($logFilePath, $entry, $flags);
        self::$logFileTruncated = true;

        if (getenv('FLUENT_VALIDATION_RECTOR_VERBOSE') === '1') {
            fwrite(STDERR, $entry);
        }
    }

    private static function skipLogFilePath(): string
    {
        $cwd = getcwd();

        if ($cwd === false) {
            // getcwd() fails in weird sandboxed environments; fall back to
            // the system temp dir so we don't crash, even though the file
            // is less useful there.
            return sys_get_temp_dir() . '/.rector-fluent-validation-skips.log';
        }

        return $cwd . '/.rector-fluent-validation-skips.log';
    }
}

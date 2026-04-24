<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector\Concerns;

use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Class_;
use Rector\Rector\AbstractRector;
use ReflectionClass;
use SanderMuller\FluentValidationRector\Diagnostics;

/**
 * Writes `[fluent-validation:skip]` entries to a log file. Location depends
 * on verbose mode (see `Diagnostics::skipLogPath()`):
 *
 *   - Verbose on (`FLUENT_VALIDATION_RECTOR_VERBOSE=1`): log appears in the
 *     consumer's cwd as `.rector-fluent-validation-skips.log`. STDERR mirror
 *     is enabled for local single-process runs.
 *   - Verbose off (default as of 0.5.0): log goes to a cwd-hash-scoped path
 *     under `sys_get_temp_dir()`. Never visible in the repo. RunSummary
 *     reads it for the end-of-run hint and unlinks it.
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
 * The log is truncated at the start of each Rector invocation so stale
 * entries from previous runs don't leak in.
 *
 * Truncation is coordinated via a PPID-keyed sentinel file (`*.session`)
 * with `flock` serialization: the first worker to see a sentinel whose
 * PPID doesn't match the current run's PPID truncates the log and
 * updates the sentinel; all other workers append. This is the fix for
 * the per-worker-static-flag race observed under `withParallel()`,
 * where each worker would independently truncate and wipe earlier
 * workers' entries.
 *
 * Each line is written exactly once per (rule, file, class, reason)
 * tuple to deduplicate multi-pass output within a single Rector run
 * (per-process dedupe only — across workers, duplicates are possible
 * but rare since each worker typically processes a disjoint file set).
 *
 * Known assumption: one Rector invocation at a time per cwd. Concurrent
 * invocations (e.g. an IDE-on-save hook racing a pre-push hook in the same
 * project root) each have their own PPID; each will see the other's
 * sentinel as "stale" and truncate the log mid-run. Symptom: user sees a
 * half-written or empty log they can't reproduce. Not hardened against
 * because real-world occurrence is vanishingly rare and the fix (PID
 * liveness check via `posix_kill($pid, 0)` alongside the PPID marker)
 * adds complexity for no observed demand. Documented here so future
 * debugging finds the explanation quickly.
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
     * Per-process flag: did this worker already verify its session
     * matches the sentinel (and thus the log's current contents)? Once
     * set, subsequent writes skip the sentinel check and append
     * directly. Scoped per-process — each worker runs this check once.
     */
    private static bool $logSessionVerified = false;

    /**
     * @param  bool  $verboseOnly  When true, only writes the entry in verbose
     *                             mode. Use for non-actionable skips: success
     *                             cases ("already has trait", "parent inherits
     *                             trait"), user-customized-docblock, statically
     *                             unresolvable rule payloads (legit escape
     *                             hatches like `->rule(Password::default())`).
     *                             Default false preserves the actionable-skip
     *                             path for bugs/config mismatches users need
     *                             to see.
     */
    private function logSkip(Class_ $class, string $reason, bool $verboseOnly = false): void
    {
        $className = $class->namespacedName?->toString()
            ?? ($class->name instanceof Identifier ? $class->name->toString() : 'anonymous');

        $this->writeSkipEntry($className, $reason, $verboseOnly);
    }

    /**
     * Variant for callers that have a class name string but no `Class_`
     * AST node — e.g. `MethodCall`-driven rectors that resolve the
     * enclosing class via PHPStan scope rather than parent-walking.
     */
    private function logSkipByName(string $className, string $reason, bool $verboseOnly = false): void
    {
        $this->writeSkipEntry($className, $reason, $verboseOnly);
    }

    private function writeSkipEntry(string $className, string $reason, bool $verboseOnly = false): void
    {
        if ($verboseOnly && ! Diagnostics::isVerbose()) {
            return;
        }

        $file = $this->getFile()->getFilePath();
        $rule = (new ReflectionClass($this))->getShortName();

        $key = $rule . '|' . $file . '|' . $className . '|' . $reason;

        if (isset(self::$loggedSkips[$key])) {
            return;
        }

        self::$loggedSkips[$key] = true;

        $entry = sprintf("[fluent-validation:skip] %s %s (%s): %s\n", $rule, $className, $file, $reason);

        self::ensureLogSessionFreshness();

        @file_put_contents(Diagnostics::skipLogPath(), $entry, FILE_APPEND | LOCK_EX);

        if (Diagnostics::isVerbose()) {
            fwrite(STDERR, $entry);
        }
    }

    /**
     * Truncate the skip log when this is the first write of a new
     * Rector invocation. "New invocation" is detected via a session
     * sentinel file that stores the parent PID (all workers under
     * `withParallel()` share the same PPID — the main Rector process
     * that forks them). If the sentinel is missing or its PID doesn't
     * match the current PPID, we're in a new run and truncate the log.
     *
     * Serialized via `flock(LOCK_EX)` on the sentinel itself so two
     * workers racing on the "first write" moment won't both truncate.
     * Each worker runs the check once per process; subsequent writes
     * skip straight to append.
     *
     * On non-POSIX platforms where `posix_getppid` isn't available, falls
     * back to an mtime-based staleness heuristic — if the sentinel's
     * last-modified time is older than `STALE_SENTINEL_SECONDS`, treat
     * as a new run. Rector invocations typically complete within that
     * window for the sentinel check to be reliable.
     *
     * Without this, per-process static flags cause later workers to
     * truncate entries written by earlier workers — observed by mijntp
     * under `withParallel(300, 15, 15)` as a deterministic data loss
     * where only the last-writing worker's entries survived.
     */
    private static function ensureLogSessionFreshness(): void
    {
        if (self::$logSessionVerified) {
            return;
        }

        $sentinelPath = Diagnostics::skipLogSentinelPath();
        $logPath = Diagnostics::skipLogPath();
        $sessionMarker = self::currentLogSessionMarker();

        $handle = @fopen($sentinelPath, 'c+b');

        if ($handle === false) {
            // Sentinel unavailable (unwritable cwd, etc.); degrade gracefully
            // by skipping the truncation check and appending to whatever log
            // exists. Worst case: entries accumulate across runs, which is
            // strictly better than losing entries within a single run.
            self::$logSessionVerified = true;

            return;
        }

        try {
            if (! flock($handle, LOCK_EX)) {
                self::$logSessionVerified = true;

                return;
            }

            rewind($handle);
            $existing = stream_get_contents($handle);
            $existing = $existing === false ? '' : trim($existing);

            if (self::isSessionStale($existing, $sessionMarker, $sentinelPath)) {
                @file_put_contents($logPath, '', LOCK_EX);

                ftruncate($handle, 0);
                rewind($handle);
                fwrite($handle, $sessionMarker);
            }

            // Touch the sentinel so the mtime-based fallback stays fresh
            // while this rector run is still producing log entries.
            @touch($sentinelPath);

            // Flush PHP's userland stream buffer to the OS BEFORE releasing
            // the advisory lock. Without this, another worker can acquire
            // the lock, fopen/read the sentinel, and see empty/stale content
            // (the sentinel write is still buffered on our side), decide the
            // session is "stale", and re-truncate the log — wiping entries
            // the first worker already appended. `flock(LOCK_UN)` is POSIX
            // advisory-only and does NOT imply a buffer flush; `fclose`
            // implicitly flushes but runs in finally, i.e. AFTER unlock.
            // Caught by mijntp: under `withParallel()` on macOS/APFS the
            // unflushed window was wide enough to fire 100% of the time on
            // small file counts (2-file bail-only runs produced zero log
            // output across 3 consecutive runs).
            fflush($handle);

            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }

        self::$logSessionVerified = true;
    }

    /**
     * How long a sentinel can sit idle before we consider it a prior run
     * (mtime-based fallback path). Rector invocations on realistic
     * codebases complete in well under this window; consumers running
     * exceptionally long rector runs (>5 min) on non-POSIX systems may
     * see the fallback decline to truncate across back-to-back runs,
     * which is an acceptable degradation vs. losing entries.
     */
    private const STALE_SENTINEL_SECONDS = 300;

    /**
     * Decide whether an existing sentinel belongs to a prior rector
     * invocation. On POSIX the PPID-based check is authoritative: if the
     * stored marker matches the current PPID, this is the same run. The
     * mtime fallback kicks in only when PPID is unavailable (Windows,
     * non-POSIX) or when the sentinel contains an mtime-format marker.
     */
    private static function isSessionStale(string $existing, string $current, string $sentinelPath): bool
    {
        if ($existing === '') {
            return true;
        }

        if (str_starts_with($current, 'ppid:') && $existing === $current) {
            return false;
        }

        if (str_starts_with($current, 'mtime:') || str_starts_with($existing, 'mtime:')) {
            $mtime = @filemtime($sentinelPath);

            if ($mtime === false) {
                return true;
            }

            return (time() - $mtime) > self::STALE_SENTINEL_SECONDS;
        }

        return $existing !== $current;
    }

    /**
     * Session identifier shared by all workers in one Rector invocation.
     * PPID is stable: under `withParallel()` every worker's parent is
     * the same main Rector process; under single-process runs the
     * worker's own PPID is the shell, which is also stable for the
     * duration of that invocation.
     *
     * On non-POSIX platforms `posix_getppid` isn't available. We fall
     * back to an `mtime:` sentinel whose freshness is checked via
     * `filemtime()` in `isSessionStale()`. This is less precise than
     * PPID but avoids per-worker data loss on Windows, which would
     * otherwise trigger the same race this sentinel is meant to fix
     * (each worker has a unique PID, so PID-based markers would never
     * match across workers).
     */
    private static function currentLogSessionMarker(): string
    {
        if (function_exists('posix_getppid')) {
            return 'ppid:' . posix_getppid();
        }

        return 'mtime:' . getmypid();
    }
}

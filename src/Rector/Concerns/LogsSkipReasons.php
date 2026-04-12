<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector\Concerns;

use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Class_;
use Rector\Rector\AbstractRector;
use ReflectionClass;

/**
 * Emits `[fluent-validation:skip]` lines to stderr when the
 * `FLUENT_VALIDATION_RECTOR_VERBOSE` environment variable is set.
 *
 * Purpose: give users visibility into why a given class wasn't converted
 * without requiring them to source-dive into the rule. Only called at
 * meaningful decision points (abstract, Livewire, already-has-trait, etc.)
 * — not at every early-exit node-type mismatch, which would be too noisy.
 *
 * Enable per run:
 *
 *     FLUENT_VALIDATION_RECTOR_VERBOSE=1 vendor/bin/rector process
 *
 * Each line is printed exactly once per (rule, file, class, reason) tuple,
 * to deduplicate multi-pass output.
 *
 * @phpstan-require-extends AbstractRector
 */
trait LogsSkipReasons
{
    /** @var array<string, true> De-dup set, scoped per-process */
    private static array $loggedSkips = [];

    /**
     * Log a single skip reason to stderr.
     *
     * Must be called from a Rector rule with access to `$this->getFile()`
     * (i.e. an AbstractRector subclass).
     */
    private function logSkip(Class_ $class, string $reason): void
    {
        if (getenv('FLUENT_VALIDATION_RECTOR_VERBOSE') !== '1') {
            return;
        }

        $file = $this->getFile()->getFilePath();
        $className = $class->namespacedName?->toString()
            ?? ($class->name instanceof Identifier ? $class->name->toString() : 'anonymous');
        $rule = (new ReflectionClass($this))->getShortName();

        $key = $rule . '|' . $file . '|' . $className . '|' . $reason;

        if (isset(self::$loggedSkips[$key])) {
            return;
        }

        self::$loggedSkips[$key] = true;

        fwrite(STDERR, "[fluent-validation:skip] {$rule} {$className} ({$file}): {$reason}\n");
    }
}

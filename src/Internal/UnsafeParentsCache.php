<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Internal;

/**
 * The shared temp file through which parallel Rector workers tell each other
 * which parent classes are unsafe to convert (a child manipulates
 * `parent::rules()` as a plain array). See the `ConvertsValidationRuleStrings`
 * docblock for the three detection layers that use it.
 *
 * The file is append-only while a run is in progress. It holds state for ONE
 * run: `RunSummary` clears it in the parent process before the workers start
 * and again at shutdown. Without that, a parent marked unsafe in an earlier
 * run stayed skipped in every later run from the same directory, even after
 * the child that caused it changed.
 *
 * @internal
 */
final class UnsafeParentsCache
{
    /**
     * Scoped to the current working directory, so concurrent runs in
     * different projects do not share state.
     */
    public static function path(): string
    {
        return sys_get_temp_dir() . '/rector-fluent-unsafe-parents-' . hash('xxh128', (string) getcwd()) . '.txt';
    }

    public static function clear(): void
    {
        $path = self::path();

        if (is_file($path)) {
            @unlink($path);
        }
    }
}

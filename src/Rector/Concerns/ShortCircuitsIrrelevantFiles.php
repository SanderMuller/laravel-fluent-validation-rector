<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector\Concerns;

use Rector\Rector\AbstractRector;

/**
 * Per-file relevance gate. Most rules in this package only fire on files
 * that contain validation-shaped tokens (`FluentRule::`, `'required'`,
 * `validate(`, etc.). In a typical consumer codebase, the vast majority
 * of files contain none of those — yet our rules still pay the per-node
 * dispatch cost (Rector visits every method call, every class, every
 * function call) because the underlying AST visitor doesn't know which
 * rules care about which files.
 *
 * This trait gives each rule a cheap text-based bail: read the file
 * content (already in memory, no I/O), `str_contains()` the rule's
 * needles, cache the answer per file path. Subsequent node dispatches
 * for the same file return in O(1).
 *
 * The cache is static so multiple rectors with overlapping needle sets
 * still share the per-needle-set decision — but the typical pattern is
 * one needle set per rule.
 *
 * False-negative risk: if a needle is misspelled or the file uses an
 * unusual encoding (`'rule' . 's'()`), the rule will silently skip. Pick
 * needles that any plausible authoring style would still emit verbatim
 * (`FluentRule`, `'required`, `extends FormRequest`, etc.).
 *
 * Rector's parallel mode runs each worker as a separate process; the
 * static cache lives per worker (correct, since each worker only sees
 * its assigned files).
 *
 * @internal
 *
 * @phpstan-require-extends AbstractRector
 */
trait ShortCircuitsIrrelevantFiles
{
    /**
     * Per-file text-needle decisions. Outer key = file path, inner key =
     * concatenated needles, value = whether any needle was found.
     *
     * @var array<string, array<string, bool>>
     */
    private static array $fileRelevanceCache = [];

    /**
     * @param  list<string>  $needles
     */
    private function currentFileContainsAny(array $needles): bool
    {
        $path = $this->getFile()->getFilePath();
        $cacheKey = implode("\0", $needles);

        if (isset(self::$fileRelevanceCache[$path][$cacheKey])) {
            return self::$fileRelevanceCache[$path][$cacheKey];
        }

        $content = $this->getFile()->getFileContent();

        foreach ($needles as $needle) {
            if (str_contains($content, $needle)) {
                return self::$fileRelevanceCache[$path][$cacheKey] = true;
            }
        }

        return self::$fileRelevanceCache[$path][$cacheKey] = false;
    }
}

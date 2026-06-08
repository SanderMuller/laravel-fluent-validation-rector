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
     * Broad needle set covering all rule-bearing surfaces this package
     * touches: a `rules()` method declaration, a Validator/`validate()`
     * call site, a `RuleSet::from()` site, the `#[FluentRules]` opt-in
     * attribute, a fluent-validation trait `use`, or any FluentRule
     * chain. A file containing none of these has nothing for any of our
     * rules to do.
     *
     * Kept as a constant on the trait so all rules share one canonical
     * needle list — drift between rules' gates is the failure mode
     * (under-gating leaves work on the table; over-gating silently
     * skips a transformation).
     *
     * @var list<string>
     */
    private const array RULE_BEARING_NEEDLES = [
        'function rules',
        'validate(',
        'validator(',
        'Validator::',
        'RuleSet::',
        'FluentRule',
        'FluentRules',
        'HasFluentRules',
        'HasFluentValidation',
    ];

    /**
     * @param  list<string>  $needles
     */
    private function currentFileContainsAny(array $needles): bool
    {
        return $this->fileContainsAnyNeedle($needles, implode("\0", $needles));
    }

    /**
     * Convenience helper: bail if the file contains nothing rule-bearing
     * any of this package's rules could act on.
     *
     * Dispatched once per ClassLike/MethodCall/StaticCall/FuncCall node across
     * every relevant file, so it passes the precomputed constant cache key
     * rather than re-imploding the 9-needle array on every call.
     */
    private function currentFileLooksRuleBearing(): bool
    {
        return $this->fileContainsAnyNeedle(self::RULE_BEARING_NEEDLES, self::ruleBearingCacheKey());
    }

    /**
     * Per-file text-needle scan, memoized by (file path, cache key). The cache
     * key identifies the needle set so callers with different needles don't
     * collide.
     *
     * @param  list<string>  $needles
     */
    private function fileContainsAnyNeedle(array $needles, string $cacheKey): bool
    {
        $path = $this->getFile()->getFilePath();

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

    /** Memoized concatenation of RULE_BEARING_NEEDLES; '' until first computed. */
    private static string $ruleBearingCacheKey = '';

    /**
     * The concatenated `RULE_BEARING_NEEDLES` cache key, computed once per
     * process. A `string`-returning helper backed by a typed static property
     * keeps the hot-path array key guaranteed-string (an inline
     * `static $k = null; $k ??= …` left PHPStan inferring a nullable key).
     */
    private static function ruleBearingCacheKey(): string
    {
        if (self::$ruleBearingCacheKey === '') {
            self::$ruleBearingCacheKey = implode("\0", self::RULE_BEARING_NEEDLES);
        }

        return self::$ruleBearingCacheKey;
    }
}

<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector\Concerns;

use PhpParser\Comment\Doc;
use PhpParser\Node\Stmt\ClassMethod;

/**
 * Normalizes a stale narrow `@return` docblock on a mutated `rules()` method.
 *
 * Context: when any body-mutation rector rewrites the array returned by
 * `rules()`, the method's pre-existing `@return` annotation may no longer
 * match the new body's inferred type. Mijntp ran 0.4.14 across a production
 * app and found 5 files with annotations like `@return array<string, StringRule>`
 * on methods whose rector-produced body now returned `array<string, ArrayRule>`
 * (because `GroupWildcardRulesToEachRector` folded wildcards into an
 * `array()->children(...)` chain, changing the terminal Rule subclass).
 * PHPStan correctly flagged every one as a type-lie.
 *
 * The fix: on mutation, if the method's existing `@return` docblock references
 * any known FluentRule-family concrete type (e.g. `StringRule`, `ArrayRule`),
 * replace the whole annotation with the standard union the fresh-emit path
 * uses — `array<string, ValidationRule|string|array<mixed>>`. The union is
 * wide enough to cover every shape any rector in this package emits, narrow
 * enough to not trip `rector/type-perfect` or `tomasvotruba/type-coverage`'s
 * "too broad, narrow your type" fixers. See the 0.4.14 changelog for the
 * rationale behind the exact union chosen.
 *
 * Docblocks that don't reference a FluentRule-family type are left alone —
 * the user may have written `@return array<string, mixed>` or a custom
 * supertype that's still correct post-mutation. Only narrow rule-typed
 * annotations are considered stale.
 */
trait NormalizesRulesDocblock
{
    /**
     * Short-name FluentRule-family types that signal a stale annotation.
     * These are the concrete classes `FluentRule::...()` factories return.
     * Matched as whole words (word boundaries) so an unrelated `Rule`
     * reference doesn't trigger replacement.
     *
     * @var list<string>
     */
    private const array FLUENT_RULE_TYPE_SHORTNAMES = [
        'FluentRule',
        'StringRule',
        'NumericRule',
        'IntegerRule',
        'ArrayRule',
        'BooleanRule',
        'DateRule',
        'EmailRule',
        'FieldRule',
        'FileRule',
        'ImageRule',
        'UrlRule',
        'UuidRule',
        'UlidRule',
        'IpRule',
        'JsonRule',
        'PasswordRule',
        'MimesRule',
        'ProhibitedRule',
    ];

    protected const string STANDARD_RULES_ANNOTATION_BODY = 'array<string, \\Illuminate\\Contracts\\Validation\\ValidationRule|string|array<mixed>>';

    /**
     * Matches a full `@return` tag — including continuation lines that belong
     * to the same tag. A PHPDoc tag's content ends at the next `@<tag>` line,
     * a blank `*`-only separator line, or the docblock terminator. Without
     * accounting for continuations, a multi-line annotation would only be
     * partially rewritten, leaving dangling continuation lines behind. Without
     * the blank-line stop, a trailing blank `*` separator (visual gap before
     * the next tag) would be consumed by the body capture and lost on
     * replacement — surfaced by collectiq dogfood (2026-04-26): `@return`
     * followed by a blank `*` line followed by another PHPDoc tag (e.g.
     * `@phpstan-*` annotations, `@param`) had its separator eaten by the
     * rewrite.
     *
     * Continuation negative lookahead `(?!\s*@|\s*\/|\s*$)` rejects:
     *   - `* @<tag>` (next PHPDoc tag)
     *   - `* /` (docblock terminator)
     *   - `*` followed by only whitespace / EOL (visual separator)
     *
     * Capture groups:
     * - Group 1: the `@return` token (kept verbatim in the replacement).
     * - Group 2: the annotation body we test for staleness and overwrite.
     */
    protected const string RETURN_TAG_PATTERN = '/(@return)\s+((?:(?:(?!\s*\*\/)[^\r\n])*(?:\n[ \t]*\*(?![ \t]*\r?\n|\s*@|\s*\/)(?:(?!\s*\*\/)[^\r\n])*)*))/';

    /**
     * Normalize the rules() method's `@return` annotation when it references a
     * stale FluentRule-family type. No-op when the method has no doc comment,
     * no `@return` tag, or an annotation body that doesn't reference a known
     * FluentRule-family type.
     *
     * Staleness is evaluated only against the `@return` annotation body — not
     * the whole docblock text — so a broad `@return array<string, mixed>`
     * paired with prose elsewhere in the docblock that mentions `StringRule`
     * (e.g. a description sentence or an unrelated tag) is preserved.
     */
    private function normalizeRulesDocblockIfStale(ClassMethod $method): bool
    {
        $doc = $method->getDocComment();

        if (! $doc instanceof Doc) {
            return false;
        }

        $text = $doc->getText();

        if (preg_match(self::RETURN_TAG_PATTERN, $text, $matches) !== 1) {
            return false;
        }

        if (! $this->annotationReferencesFluentRuleType($matches[2])) {
            return false;
        }

        $replacement = (string) preg_replace(
            self::RETURN_TAG_PATTERN,
            '$1 ' . self::STANDARD_RULES_ANNOTATION_BODY,
            $text,
            1,
        );

        if ($replacement === $text) {
            return false;
        }

        $method->setDocComment(new Doc($replacement));

        return true;
    }

    private function annotationReferencesFluentRuleType(string $annotation): bool
    {
        foreach (self::FLUENT_RULE_TYPE_SHORTNAMES as $typeName) {
            if (preg_match('/\b' . preg_quote($typeName, '/') . '\b/', $annotation) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Decides whether an extracted `@return` annotation body is the canonical
     * wide-union (optionally followed by pure-prose description) that the
     * `rules()` polish pass is allowed to narrow, or a user-customized type
     * that must be left alone.
     *
     * Accepts:
     *   - body exactly equal to `STANDARD_RULES_ANNOTATION_BODY`
     *   - body starting with `STANDARD_RULES_ANNOTATION_BODY` followed only by
     *     whitespace and plain-text prose (letters, digits, space, basic
     *     punctuation)
     *
     * Rejects any remainder containing type-syntax characters `|`, `&`, `<`,
     * `>`, `(`, `)`, `[`, `]`, `@`, or a `\\`-prefixed FQN token. Guards
     * against silently narrowing user-widened types like
     * `array<string, \Illuminate\Contracts\Validation\ValidationRule|string|array<mixed>>|\Illuminate\Support\Collection`
     * whose leading segment matches the standard body but whose trailing
     * `|\Collection` is a genuine additive union the user authored. See spec
     * `update-rules-return-type-docblock-rector.md` §4 + Codex finding #3.
     *
     * Input should already be whitespace-trimmed and have PHPDoc continuation
     * lines concatenated into a single string — the continuation-handling
     * matching is the caller's responsibility (use `RETURN_TAG_PATTERN`).
     */
    private function annotationBodyMatchesStandardUnionExactlyOrProse(string $body): bool
    {
        $body = trim($body);
        $standard = self::STANDARD_RULES_ANNOTATION_BODY;

        if ($body === $standard) {
            return true;
        }

        if (! str_starts_with($body, $standard)) {
            return false;
        }

        $remainder = substr($body, strlen($standard));

        if ($remainder === '') {
            return true;
        }

        return preg_match('/^\s+[A-Za-z][A-Za-z0-9 ,.\'\-]*$/', $remainder) === 1;
    }
}

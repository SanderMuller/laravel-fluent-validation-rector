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

    private const string STANDARD_RULES_ANNOTATION_BODY = 'array<string, \\Illuminate\\Contracts\\Validation\\ValidationRule|string|array<mixed>>';

    /**
     * Matches a full `@return` tag — including continuation lines that belong
     * to the same tag. A PHPDoc tag's content ends at the next `@<tag>` line
     * or at the docblock terminator. Without accounting for continuations, a
     * multi-line annotation would only be partially rewritten, leaving
     * dangling continuation lines behind.
     *
     * Capture groups:
     * - Group 1: the `@return` token (kept verbatim in the replacement).
     * - Group 2: the annotation body we test for staleness and overwrite.
     */
    private const string RETURN_TAG_PATTERN = '/(@return)\s+((?:[^\r\n]*(?:\n[ \t]*\*(?!\s*@|\s*\/)[^\r\n]*)*))/';

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
}

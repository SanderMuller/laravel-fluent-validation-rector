<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector\Concerns;

use PhpParser\Comment\Doc;
use PhpParser\Node\Arg;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Attribute;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Nop;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\Return_;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\Rector\AbstractRector;

/**
 * Phase 4 of the Livewire `#[Validate]` migration: collect `message:`
 * attribute args and emit a companion `messages(): array` method.
 *
 * Two source shapes:
 *
 * - **String `message: 'text'`** — applies to the whole attribute (every
 *   rule token in the same `#[Validate]`). Migrated as a single
 *   `'<propertyName>' => 'text'` entry; Laravel matches that key against
 *   any rule failing on the attribute. Documented Livewire behavior.
 * - **Array `message: ['required' => 'X', 'min' => 'Y']`** — per-rule
 *   keys. Each entry migrates to `'<propertyName>.<ruleName>' => 'text'`.
 *   When the attribute first-arg is itself a keyed-array (Livewire's
 *   `#[Validate(['todos' => …, 'todos.*' => …])]` shape), the keys can
 *   also be `<key>.<ruleName>` — preserved verbatim.
 *
 * Gated on the host rector's `$migrateMessages` bool. When `false`
 * (default), this concern's collection methods return `[]` and the
 * existing skip-log path in `ReportsLivewireAttributeArgs` continues to
 * fire — preserving legacy behavior for consumers who centralize
 * messages in lang files.
 *
 * @phpstan-require-extends AbstractRector
 */
trait MigratesAttributeMessages
{
    use ResolvesInheritedRulesVisibility;

    /**
     * Per-attribute migration status, keyed by `spl_object_id($attr)`.
     * Populated by `collectMessageEntries` pre-strip; consulted by
     * `ReportsLivewireAttributeArgs::logUnsupportedAttributeArgs` to
     * decide whether to suppress the legacy `message:` skip-log line.
     * Only attributes that actually had their `message:` arg migrated
     * are recorded — non-literal / unsupported shapes still generate
     * a legacy log so the user has a trail.
     *
     * @var array<int, true>
     */
    private array $messagesMigratedFromAttrs = [];

    /**
     * Walk every Property's attributes on the class and collect
     * `message:` arg entries for migration. Host rector calls this
     * BEFORE the rule-extraction pass that strips attributes — order
     * matters because stripped attributes are unreadable.
     *
     * Per-property: only the FIRST convertible Livewire attribute
     * contributes message entries, matching the first-wins rule
     * extraction in `extractAndStripRuleAttribute`. Migrating messages
     * from later attributes whose rules get discarded would emit
     * orphan `messages()` keys for non-existent rules.
     *
     * @return list<array{key: string, text: string}>
     */
    private function collectMessageEntries(Class_ $class): array
    {
        if (! $this->migrateMessages) {
            return [];
        }

        $this->messagesMigratedFromAttrs = [];
        $collected = [];

        foreach ($class->stmts as $stmt) {
            if (! $stmt instanceof Property) {
                continue;
            }

            foreach ($stmt->props as $propertyItem) {
                $propertyName = $propertyItem->name->toString();

                foreach ($this->collectMessagesForProperty($stmt, $propertyName) as $entry) {
                    $collected[] = $entry;
                }
            }
        }

        return $collected;
    }

    /**
     * Walk one Property's attributes and return the message entries
     * extracted from the FIRST convertible Livewire attribute. Marks
     * the source attribute in `messagesMigratedFromAttrs` when a
     * non-empty entry list is produced.
     *
     * "Convertible" must match `extractAndStripRuleAttribute`'s own
     * first-wins selection (which skips unconvertible attributes via
     * `convertAttributeToEntries` returning null). Anchoring on a
     * non-convertible attribute would orphan a later convertible
     * attribute's `message:` arg — its rules get migrated and stripped,
     * but its messages never reach `messages()`. The cheap shape
     * predicate (first-arg value is String_ or Array_) approximates
     * the full convertibility check without re-running the conversion.
     *
     * @return list<array{key: string, text: string}>
     */
    private function collectMessagesForProperty(Property $property, string $propertyName): array
    {
        $entries = [];
        $anchored = false;

        foreach ($property->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                if (! $this->isLivewireRuleAttribute($attr)) {
                    continue;
                }

                if (! $this->attributeIsConvertibleShape($attr)) {
                    continue;
                }

                if ($anchored) {
                    continue;
                }

                $extracted = $this->extractMessageEntries($attr, $propertyName);
                $anchored = true;
                // null = no message: arg present (no log change).
                // [] = message: arg present but unsupported shape
                // (non-literal / mixed); legacy skip-log path stays
                // active because the attr is NOT added to the migrated
                // set below.
                if ($extracted === null) {
                    continue;
                }

                if ($extracted === []) {
                    continue;
                }

                $this->messagesMigratedFromAttrs[spl_object_id($attr)] = true;
                $entries = $extracted;
            }
        }

        return $entries;
    }

    /**
     * Whether the attribute's first arg is a shape that
     * `convertAttributeToEntries` can convert (String_ rule string or
     * Array_ keyed/list-form rule set). Cheaply rejects shapes the
     * rule-extractor would skip (closures, variables, missing args)
     * so the message-anchor lands on the same attribute the rule-
     * extractor will pick up. Doesn't guarantee conversion succeeds
     * — false positives mean we anchor on an attribute whose rules
     * fail later, leaving messages without a corresponding `rules()`
     * entry; the install path handles that gracefully (entries with
     * keys that don't match any attribute are still valid Laravel
     * messages: arrays).
     */
    private function attributeIsConvertibleShape(Attribute $attr): bool
    {
        if ($attr->args === []) {
            return false;
        }

        $first = $attr->args[0];

        if (! $first instanceof Arg) {
            return false;
        }

        return $first->value instanceof String_ || $first->value instanceof Array_;
    }

    /**
     * Whether the given attribute's `message:` arg was successfully
     * migrated by `collectMessageEntries`. Used by the report concern
     * to decide whether the legacy `message:` skip-log line should be
     * suppressed. Returns false when migration is off, the attribute
     * had no `message:` arg, or the shape was unsupported.
     */
    private function attributeMessageWasMigrated(Attribute $attr): bool
    {
        return isset($this->messagesMigratedFromAttrs[spl_object_id($attr)]);
    }

    /**
     * Extract message entries from a single attribute, scoped to the
     * given property name (the attribute target).
     *
     * Returns `null` when no `message:` arg is present (caller treats
     * the attribute as anchor-only — no migration, no log change).
     * Returns `[]` when a `message:` arg IS present but its shape isn't
     * supported for migration (caller leaves the legacy skip-log path
     * active).
     * Returns a non-empty list when migration succeeded.
     *
     * @return list<array{key: string, text: string}>|null
     */
    private function extractMessageEntries(
        Attribute $attr,
        string $propertyName,
    ): ?array {
        if (! $this->migrateMessages) {
            return null;
        }

        foreach ($attr->args as $arg) {
            if (! $arg->name instanceof Identifier) {
                continue;
            }

            if ($arg->name->toString() !== 'message') {
                continue;
            }

            if ($arg->value instanceof String_) {
                // Whole-attribute message — Laravel matches the
                // attribute-only key against any rule failure.
                return [['key' => $propertyName, 'text' => $arg->value->value]];
            }

            if ($arg->value instanceof Array_) {
                // extractKeyedMessages returns [] when the array shape
                // isn't fully literal — propagate the unsupported
                // signal so the legacy skip-log fires.
                return $this->extractKeyedMessages($arg->value, $propertyName);
            }

            // Non-literal message value (variable, concat, etc.) —
            // can't statically migrate; signal "unsupported".
            return [];
        }

        return null;
    }

    /**
     * Walk a `message: [...]` array literal and produce one entry per
     * key. Keys without a `.` are interpreted as rule names and prefixed
     * with the attribute's property name; keys with a `.` are passed
     * through verbatim (Livewire allows full-path keys when the
     * attribute first-arg is itself a keyed-array of sub-attribute rules).
     *
     * @return list<array{key: string, text: string}>
     */
    private function extractKeyedMessages(Array_ $messages, string $propertyName): array
    {
        $entries = [];

        foreach ($messages->items as $item) {
            if (! $item instanceof ArrayItem) {
                return [];
            }

            if (! $item->key instanceof String_ || ! $item->value instanceof String_) {
                // Non-literal key/value — bail to legacy skip-log.
                return [];
            }

            $rawKey = $item->key->value;

            if ($rawKey === '') {
                return [];
            }

            $finalKey = str_contains($rawKey, '.')
                ? $rawKey
                : $propertyName . '.' . $rawKey;

            $entries[] = ['key' => $finalKey, 'text' => $item->value->value];
        }

        return $entries;
    }

    /**
     * Install collected message entries as a `messages(): array` method on
     * the class. Mirrors `installRulesMethod` in shape: merges into an
     * existing simple `return [...]` method when present, generates a new
     * one otherwise.
     *
     * @param  list<array{key: string, text: string}>  $entries
     * @return bool true when the method was generated or merged; false on
     *              no-op or bail (e.g. existing method isn't mergeable)
     */
    /**
     * Preflight: would `installMessagesMethod` succeed if called with
     * a non-empty entry list right now? Used by the host rector BEFORE
     * stripping attributes — when this returns false and entries exist,
     * the whole conversion must abort so the source `message:` data
     * isn't lost (attribute strip + failed messages() install would
     * leave the class with `rules()` but no migrated messages).
     */
    private function canInstallMessagesMethod(Class_ $class): bool
    {
        $existing = $this->findMessagesMethod($class);

        if ($existing instanceof ClassMethod) {
            return $this->trivialReturnArrayMethodBody($existing) instanceof Return_;
        }

        return $this->resolveGeneratedRulesVisibility($class) !== null;
    }

    /**
     * Returns the single trivial `return [...]` statement when the
     * method body is exactly that — a single executable statement that
     * is a Return_ wrapping an Array_ literal. Comments / Nop nodes
     * are ignored. Multi-return bodies, conditional returns, builder
     * loops, and `return $cached` style methods all fail the check.
     *
     * Used by `canInstallMessagesMethod` (preflight before stripping
     * attributes) and `mergeIntoExistingMessagesMethod` (mutation
     * point) to share a single mergeability predicate. Diverging
     * predicates would let preflight green-light a method the merge
     * couldn't actually patch, leaving conditional return branches
     * with no migrated message.
     */
    private function trivialReturnArrayMethodBody(ClassMethod $method): ?Return_
    {
        $found = null;

        foreach ($method->stmts ?? [] as $stmt) {
            if ($stmt instanceof Nop) {
                continue;
            }

            if (! $stmt instanceof Return_) {
                return null;
            }

            if ($found instanceof Return_) {
                // Multiple top-level returns — pattern looks like
                // `return cond ? a : b` collapsed via short-circuit
                // earlier returns. Refuse to mutate.
                return null;
            }

            if (! $stmt->expr instanceof Array_) {
                return null;
            }

            $found = $stmt;
        }

        return $found;
    }

    /**
     * @param  list<array{key: string, text: string}>  $entries
     */
    private function installMessagesMethod(Class_ $class, array $entries): bool
    {
        if ($entries === []) {
            return false;
        }

        $existing = $this->findMessagesMethod($class);

        if ($existing instanceof ClassMethod) {
            return $this->mergeIntoExistingMessagesMethod($existing, $entries);
        }

        $visibility = $this->resolveGeneratedRulesVisibility($class);

        if ($visibility === null) {
            return false;
        }

        $items = array_map(
            static fn (array $entry): ArrayItem => new ArrayItem(
                new String_($entry['text']),
                new String_($entry['key']),
            ),
            $entries,
        );

        $array = new Array_($items);
        $array->setAttribute(AttributeKey::NEWLINED_ARRAY_PRINT, true);

        $method = new ClassMethod(
            new Identifier('messages'),
            [
                'flags' => $visibility,
                'returnType' => new Identifier('array'),
                'stmts' => [new Return_($array)],
            ],
        );

        $method->setDocComment(new Doc(
            "/**\n * @return array<string, string>\n */",
        ));

        $lastStmt = end($class->stmts);

        if ($lastStmt !== false && ! $lastStmt instanceof Nop) {
            $class->stmts[] = new Nop();
        }

        $class->stmts[] = $method;

        return true;
    }

    private function findMessagesMethod(Class_ $class): ?ClassMethod
    {
        foreach ($class->stmts as $stmt) {
            if ($stmt instanceof ClassMethod && $this->isName($stmt, 'messages')) {
                return $stmt;
            }
        }

        return null;
    }

    /**
     * @param  list<array{key: string, text: string}>  $entries
     */
    private function mergeIntoExistingMessagesMethod(ClassMethod $method, array $entries): bool
    {
        $returnStmt = $this->trivialReturnArrayMethodBody($method);

        if (! $returnStmt instanceof Return_ || ! $returnStmt->expr instanceof Array_) {
            // Non-trivial existing messages() body (multi-return,
            // conditional, builder loop, `return $cached`) — leave alone,
            // fall through to caller's skip-log. Same predicate
            // `canInstallMessagesMethod` uses for preflight, so the two
            // can't disagree on what's mergeable.
            return false;
        }

        // Dedup: skip entries whose key is already present in the
        // existing array. Preserves user-authored messages without
        // overwriting them.
        $existingKeys = [];
        $items = [];

        foreach ($returnStmt->expr->items as $item) {
            if ($item instanceof ArrayItem) {
                $items[] = $item;

                if ($item->key instanceof String_) {
                    $existingKeys[$item->key->value] = true;
                }
            }
        }

        $appended = false;

        foreach ($entries as $entry) {
            if (isset($existingKeys[$entry['key']])) {
                continue;
            }

            $items[] = new ArrayItem(
                new String_($entry['text']),
                new String_($entry['key']),
            );
            $appended = true;
        }

        if (! $appended) {
            return false;
        }

        // Replace the Array_ node entirely so Rector treats the result
        // as a new array — needed for NEWLINED_ARRAY_PRINT to take
        // effect. Mutating the existing array's items list in-place
        // preserves the original printer-token positions, which would
        // collapse the merged entries onto a single line.
        $newArray = new Array_($items);
        $newArray->setAttribute(AttributeKey::NEWLINED_ARRAY_PRINT, true);

        $returnStmt->expr = $newArray;

        return true;
    }
}

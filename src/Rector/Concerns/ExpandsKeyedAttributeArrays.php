<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector\Concerns;

use PhpParser\Node\ArrayItem;
use PhpParser\Node\Attribute;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Property;
use Rector\Rector\AbstractRector;

/**
 * Detect and expand Livewire v3's keyed-array `#[Validate]` attribute shape
 * (`['todos' => 'required', 'todos.*' => 'min:3']`) into per-key `rules()`
 * entries. The list-array and string shapes stay on the single-chain path
 * handled by the host rector; this concern only fires for keyed-first-arg.
 *
 * Composition contract (host rector must provide):
 *
 * - `convertSingleValueRuleArg(Expr, Property, Class_): ?Expr` — converts the
 *   per-key value (String_ or Array_) to a FluentRule chain via the shared
 *   converter pipeline.
 * - `logUnsupportedAttributeArgs(Attribute, Property, Class_): void` — logs
 *   the attribute's other named args (`as:`, `message:`, etc.) which are not
 *   expanded in Phase 1; deferred to Phase 3 of the spec.
 * - `firstPropertyName(Property): string` — for skip-log messages.
 *
 * @internal
 *
 * @phpstan-require-extends AbstractRector
 */
trait ExpandsKeyedAttributeArrays
{
    use LogsSkipReasons;

    private function isKeyedArrayAttribute(Array_ $array): bool
    {
        foreach ($array->items as $item) {
            if ($item instanceof ArrayItem && $item->key instanceof String_) {
                return true;
            }
        }

        return false;
    }

    /**
     * Expand a keyed-first-arg attribute into one entry per key. Fails closed
     * on unconvertible values — partial conversion would silently drop rules.
     *
     * Downstream `GroupWildcardRulesToEachRector` folds `.*` keys into nested
     * `->each(...)` / `->children(...)` chains when the SET list runs it after
     * this rector, so flat entries are the correct intermediate shape.
     *
     * @return list<array{name: string, expr: Expr}>|null
     */
    private function convertKeyedAttributeToEntries(Array_ $array, Attribute $attr, Property $property, Class_ $class): ?array
    {
        $entries = [];

        foreach ($array->items as $item) {
            $entry = $this->convertKeyedAttributeItem($item, $property, $class);

            if ($entry === null) {
                return null;
            }

            $entries[] = $entry;
        }

        // Named args (`as:`, `message:`, etc.) on a keyed-first-arg attribute
        // are themselves array-keyed maps in Livewire v3's documented shape.
        // Array-form expansion of those args is Phase 3 scope; for now log
        // anything we see so the consumer gets a pointer.
        $this->logUnsupportedAttributeArgs($attr, $property, $class);

        return $entries === [] ? null : $entries;
    }

    /**
     * @return array{name: string, expr: Expr}|null
     */
    private function convertKeyedAttributeItem(mixed $item, Property $property, Class_ $class): ?array
    {
        if (! $item instanceof ArrayItem) {
            return null;
        }

        if (! $item->key instanceof String_) {
            $this->logSkip($class, sprintf(
                '#[Rule] attribute on property $%s mixes keyed and positional entries — bailing to avoid partial conversion',
                $this->firstPropertyName($property),
            ));

            return null;
        }

        // Reject numeric-string keys (`['0' => 'required']`). PHP internally
        // normalizes `['0' => …]` to an integer key which the literal
        // list-array path would classify positionally; PhpParser preserves the
        // String_ node as written, so our keyed-shape detector matches. But
        // a numeric key doesn't refer to a meaningful field path on the
        // annotated property and would synthesise `rules()` entries for
        // `'0'` / `'1'` / etc. that have nothing to do with the source
        // attribute. Fail closed — a visible no-op is safer than
        // silently manufacturing top-level numeric rule keys.
        if (is_numeric($item->key->value)) {
            $this->logSkip($class, sprintf(
                '#[Rule] attribute on property $%s uses numeric keyed entries (key %s) — not a documented Livewire shape, bailing',
                $this->firstPropertyName($property),
                $item->key->value,
            ));

            return null;
        }

        $fluent = $this->convertSingleValueRuleArg($item->value, $property, $class);

        if (! $fluent instanceof Expr) {
            $this->logSkip($class, sprintf(
                '#[Rule] attribute on property $%s has unconvertible value for key %s — bailing to avoid partial conversion',
                $this->firstPropertyName($property),
                $item->key->value,
            ));

            return null;
        }

        return [
            'name' => $item->key->value,
            'expr' => $fluent,
        ];
    }

    abstract private function convertSingleValueRuleArg(Expr $value, Property $property, Class_ $class): ?Expr;

    abstract private function logUnsupportedAttributeArgs(Attribute $attr, Property $property, Class_ $class): void;

    abstract private function firstPropertyName(Property $property): string;
}

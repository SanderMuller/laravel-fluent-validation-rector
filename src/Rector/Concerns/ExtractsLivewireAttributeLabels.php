<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector\Concerns;

use PhpParser\Node\Arg;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Attribute;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use Rector\Rector\AbstractRector;

/**
 * Pull Livewire's label-like named args off an attribute and fold them into
 * FluentRule `->label()` calls.
 *
 * Livewire v3 treats `as:` and `attribute:` as synonyms for the field
 * display name used in validation error messages; FluentRule's `->label()`
 * is the equivalent. When both synonyms are present with differing values,
 * `attribute:` wins on conflict — precedence is deterministic and
 * independent of source ordering. Picking `attribute:` as the winner
 * matches Laravel's own `attribute` naming for custom attribute display
 * names (used in `Validator::make`'s fourth arg); `as:` is Livewire's
 * shorter alias.
 *
 * String-form (`as: '…'` / `attribute: '…'`) applies to single-chain
 * extractions via `extractRootLabel()`. Array-form (`as: [key => label]`
 * / `attribute: [key => label]`) applies per-entry to keyed-first-arg
 * expansions via `extractKeyedLabels()` + `applyKeyedLabels()`.
 *
 * @phpstan-require-extends AbstractRector
 */
trait ExtractsLivewireAttributeLabels
{
    private function extractRootLabel(Attribute $attr): ?string
    {
        $asLabel = null;
        $attributeLabel = null;

        foreach ($attr->args as $arg) {
            if (! $arg->name instanceof Identifier) {
                continue;
            }

            if (! $arg->value instanceof String_) {
                continue;
            }

            $name = $arg->name->toString();

            if ($name === 'as' && $asLabel === null) {
                $asLabel = $arg->value->value;
            } elseif ($name === 'attribute' && $attributeLabel === null) {
                $attributeLabel = $arg->value->value;
            }
        }

        return $attributeLabel ?? $asLabel;
    }

    /**
     * @return array<string, string>
     */
    private function extractKeyedLabels(Attribute $attr): array
    {
        $asMap = $this->collectLabelMapFromNamedArg($attr, 'as');
        $attributeMap = $this->collectLabelMapFromNamedArg($attr, 'attribute');

        return $attributeMap + $asMap;
    }

    /**
     * Apply `->label($match)` to each keyed-array entry whose `name` is a
     * key in the label map. Entries without a matching label pass through
     * untouched. Run as the last step of keyed-first-arg extraction so the
     * label wraps the fully-converted rule chain.
     *
     * @param  list<array{name: string, expr: Expr}>  $entries
     * @param  array<string, string>  $labels
     * @return list<array{name: string, expr: Expr}>
     */
    private function applyKeyedLabels(array $entries, array $labels): array
    {
        if ($labels === []) {
            return $entries;
        }

        return array_map(function (array $entry) use ($labels): array {
            $label = $labels[$entry['name']] ?? null;

            if ($label === null) {
                return $entry;
            }

            return [
                'name' => $entry['name'],
                'expr' => new MethodCall($entry['expr'], new Identifier('label'), [
                    new Arg(new String_($label)),
                ]),
            ];
        }, $entries);
    }

    /**
     * @return array<string, string>
     */
    private function collectLabelMapFromNamedArg(Attribute $attr, string $argName): array
    {
        foreach ($attr->args as $arg) {
            if (! $arg->name instanceof Identifier) {
                continue;
            }

            if ($arg->name->toString() !== $argName) {
                continue;
            }

            if (! $arg->value instanceof Array_) {
                continue;
            }

            $map = [];

            foreach ($arg->value->items as $item) {
                if (! $item instanceof ArrayItem) {
                    continue;
                }

                if (! $item->key instanceof String_) {
                    continue;
                }

                if (! $item->value instanceof String_) {
                    continue;
                }

                $map[$item->key->value] = $item->value->value;
            }

            return $map;
        }

        return [];
    }
}

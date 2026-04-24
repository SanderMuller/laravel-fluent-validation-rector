<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector\Concerns;

use PhpParser\Node\Arg;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Property;
use Rector\Rector\AbstractRector;

/**
 * Predicts the rule keys a Livewire `#[Validate]` / `#[Rule]` attribute
 * would emit into `rules()` without running the full extract-and-strip
 * path. Used by the partial-overlap decision in
 * `ConvertLivewireRuleAttributeRector` to check whether a property's attrs
 * would produce keys that collide with explicit `$this->validate([...])`
 * keys — a collision forces the property to stay as attrs (partial mode).
 *
 * Checking property name alone would miss keyed-first-arg attrs like
 * `#[Validate(['todos' => ..., 'todos.*' => ...])]` whose effective keys
 * differ from the annotated property (Codex review 2026-04-24 caught this
 * as a HIGH miscompile risk in partial mode).
 *
 * Host contract:
 *   - `isLivewireRuleAttribute(Attribute): bool` from `DetectsLivewireRuleAttributes`
 *   - `isKeyedArrayAttribute(Array_): bool` from `ExpandsKeyedAttributeArrays`
 *
 * @phpstan-require-extends AbstractRector
 */
trait PredictsLivewireAttributeEmitKeys
{
    /**
     * @return list<string>
     */
    private function predictEmitKeysForProperty(Property $property): array
    {
        $keys = [];

        foreach ($property->attrGroups as $group) {
            foreach ($group->attrs as $attr) {
                if (! $this->isLivewireRuleAttribute($attr)) {
                    continue;
                }

                $firstArg = $attr->args[0] ?? null;

                if ($firstArg instanceof Arg
                    && $firstArg->value instanceof Array_
                    && $this->isKeyedArrayAttribute($firstArg->value)) {
                    foreach ($firstArg->value->items as $item) {
                        if ($item instanceof ArrayItem && $item->key instanceof String_) {
                            $keys[] = $item->key->value;
                        }
                    }

                    continue;
                }

                foreach ($property->props as $propertyItem) {
                    $keys[] = $propertyItem->name->toString();
                }
            }
        }

        return array_values(array_unique($keys));
    }
}

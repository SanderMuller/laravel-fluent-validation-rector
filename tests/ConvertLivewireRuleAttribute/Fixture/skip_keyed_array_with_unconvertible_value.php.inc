<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\Fixture;

use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Fails-closed pin: if any key's value can't convert to a FluentRule chain,
 * the whole attribute is left untouched and skip-logged. Partial conversion
 * would silently drop rules, which is worse than a visible no-op.
 *
 * The integer value here is the sentinel unconvertible shape —
 * `convertSingleValueRuleArg` only knows how to handle `String_` or `Array_`
 * first-positional values; any other expression type (integers, variables,
 * constants) returns null, and `convertKeyedAttributeToEntries` bails on
 * the first null rather than emit a partial rules map.
 */
final class KeyedArrayWithUnconvertibleValue extends Component
{
    #[Validate([
        'field_a' => 'required|string',
        'field_b' => 42,
    ])]
    public string $field_a = '';

    public string $field_b = '';
}

?>

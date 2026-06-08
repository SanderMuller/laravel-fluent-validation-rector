<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

// Regression guard: a NON-redundant wildcard parent (`address.*` carries a
// type-specific `min(2)`) alongside ONLY fixed children (`address.street`,
// `address.city`) and NO wildcard children (`address.*.x`). Grouping must
// bail here — folding would consume the `address.*` entry and silently drop
// its `min(2)` constraint. (Before the `$eachItems !== []` guard was removed
// from collectGroupChainItems, this folded to `->children([...])` and lost the
// `address.*` rule entirely.)
class NonRedundantWildcardFixedChildrenOnlyRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'address' => FluentRule::field()->required(),
            'address.*' => FluentRule::string()->min(2),
            'address.street' => FluentRule::string()->required(),
            'address.city' => FluentRule::string()->required(),
        ];
    }
}

?>

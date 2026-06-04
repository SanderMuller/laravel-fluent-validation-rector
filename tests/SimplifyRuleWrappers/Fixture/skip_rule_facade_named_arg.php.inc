<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use Illuminate\Validation\Rule;
use SanderMuller\FluentValidation\FluentRule;

class SkipRuleFacadeNamedArg
{
    public function rules(bool $cond): array
    {
        // Named args can't be threaded positionally into the native fluent
        // method — the parameter names differ between the `Rule::` facade
        // and the FluentRule builder. `parseRuleFacadeCall` refuses to
        // rewrite any facade call carrying a named argument.
        return [
            'a' => FluentRule::string()->rule(Rule::requiredIf(callback: $cond)),
            'b' => FluentRule::string()->rule(Rule::in(values: ['x', 'y'])),
        ];
    }
}

?>

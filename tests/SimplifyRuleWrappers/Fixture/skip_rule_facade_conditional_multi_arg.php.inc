<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use Illuminate\Validation\Rule;
use SanderMuller\FluentValidation\FluentRule;

class SkipRuleFacadeConditionalMultiArg
{
    public function rules(bool $cond): array
    {
        // Laravel's `Rule::requiredIf($callback)` is single-arg — it takes
        // one `Closure|bool` condition. A multi-arg call isn't valid facade
        // usage and would TypeError after lowering to the `(Closure|bool)`
        // fluent overload, so `parseRuleFacadeCall` refuses to rewrite.
        return [
            'a' => FluentRule::string()->rule(Rule::requiredIf($cond, 'extra')),
            'b' => FluentRule::string()->rule(Rule::excludeUnless($cond, 'x', 'y')),
        ];
    }
}

?>

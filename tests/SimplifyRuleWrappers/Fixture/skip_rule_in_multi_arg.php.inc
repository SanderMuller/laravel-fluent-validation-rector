<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use Illuminate\Validation\Rule;
use SanderMuller\FluentValidation\FluentRule;

class SkipRuleInMultiArg
{
    public function rules(): array
    {
        // Laravel's `Rule::in($values)` is single-arg; multi-arg form
        // silently ignores trailing args. Native `->in($values)` would
        // ArgumentCountError on multi-arg input — refuse to rewrite.
        return [
            'a' => FluentRule::string()->rule(Rule::in('a', 'b', 'c')),
            'b' => FluentRule::string()->rule(Rule::notIn('x', 'y')),
        ];
    }
}

?>

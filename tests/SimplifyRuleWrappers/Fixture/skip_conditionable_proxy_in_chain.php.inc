<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use Illuminate\Validation\Rule;
use SanderMuller\FluentValidation\FluentRule;

class SkipConditionableProxy
{
    public function rules(bool $strict): array
    {
        // Single-arg when() returns HigherOrderWhenProxy — receiver type
        // is no longer StringRule at the ->rule(...) call site.
        return [
            'name' => FluentRule::string()->when($strict)->rule(Rule::in(['a', 'b'])),
            'other' => FluentRule::numeric()->unless($strict)->rule('min:3'),
        ];
    }
}

?>

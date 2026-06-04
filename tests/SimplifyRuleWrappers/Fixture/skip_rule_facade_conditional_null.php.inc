<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use Illuminate\Validation\Rule;
use SanderMuller\FluentValidation\FluentRule;

class SkipRuleFacadeConditionalNull
{
    public function rules(): array
    {
        // `Rule::requiredIf(null)` is valid Laravel — RequiredIf's ctor
        // normalizes a null condition to `false`. The native fluent method
        // is typed `Closure|bool|string`, so `->requiredIf(null)` would
        // TypeError at runtime. Refuse to rewrite a literal-null condition;
        // the escape hatch stays.
        return [
            'a' => FluentRule::string()->rule(Rule::requiredIf(null)),
            'b' => FluentRule::string()->rule(Rule::requiredUnless(null)),
        ];
    }
}

?>

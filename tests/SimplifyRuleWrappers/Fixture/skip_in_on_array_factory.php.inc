<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use Illuminate\Validation\Rule;
use SanderMuller\FluentValidation\FluentRule;

class SkipInOnArrayFactory
{
    public function rules(): array
    {
        // ArrayRule does not use HasEmbeddedRules — has no in() method.
        // Must stay as ->rule(...) escape hatch.
        return [
            'tags' => FluentRule::array()->rule(Rule::in(['red', 'blue'])),
            'files' => FluentRule::file()->rule('in:jpg,png'),
            'flag' => FluentRule::boolean()->rule(Rule::notIn(['weird'])),
        ];
    }
}

?>

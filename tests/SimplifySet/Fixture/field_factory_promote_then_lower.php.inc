<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifySet\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class FieldFactoryPromoteThenLower
{
    public function rules(): array
    {
        return [
            // PromoteFieldFactoryRector: field() → string() because the ->rule() payloads
            // intersect to {StringRule}. SimplifyRuleWrappersRector then lowers the
            // ->rule() escape-hatches to native methods on the promoted StringRule.
            'slug' => FluentRule::field()->rule('max:61')->rule('regex:/^[a-z]+$/'),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifySet\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class FieldFactoryPromoteThenLower
{
    public function rules(): array
    {
        return [
            // PromoteFieldFactoryRector: field() → string() because the ->rule() payloads
            // intersect to {StringRule}. SimplifyRuleWrappersRector then lowers the
            // ->rule() escape-hatches to native methods on the promoted StringRule.
            'slug' => FluentRule::regex('/^[a-z]+$/')->max(61),
        ];
    }
}

?>

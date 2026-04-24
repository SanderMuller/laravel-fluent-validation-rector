<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class SkipConditionableProxyInChain
{
    public function rules(): array
    {
        $condition = true;

        return [
            // `->when()` returns a HigherOrderWhenProxy, not the typed rule.
            // Promoting field() → string() here would add Laravel's implicit
            // 'string' rule to validation output while SimplifyRuleWrappersRector
            // correctly bails on the ->rule() call downstream — user gets a
            // behavior change they didn't ask for. Bail on any conditionable hop.
            'slug' => FluentRule::field()->when($condition, fn ($r) => $r->required())->rule('max:61')->rule('regex:/^[a-z]+$/'),
        ];
    }
}

?>

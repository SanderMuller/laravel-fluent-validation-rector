<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\RuleSet;

// 0.19.1 cross-fold-path matrix cell: bare-literal `'*.foo'` keys
// (existing fold path's territory) × RuleSet::from() wrapper (new
// descent). Confirms the existing literal-fold path runs cleanly
// inside the wrapper, not just the new const-keyed shape. Pins
// "RuleSet::from() descent is shape-agnostic for what's inside the
// array" as a contract.
final class BareLiteralInsideRuleSetFromRequest extends FormRequest
{
    public function rules(): RuleSet
    {
        return RuleSet::from([
            '*.foo' => FluentRule::string()->required(),
            '*.bar' => FluentRule::integer()->min(0),
        ]);
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\RuleSet;

// 0.19.1 cross-fold-path matrix cell: bare-literal `'*.foo'` keys
// (existing fold path's territory) × RuleSet::from() wrapper (new
// descent). Confirms the existing literal-fold path runs cleanly
// inside the wrapper, not just the new const-keyed shape. Pins
// "RuleSet::from() descent is shape-agnostic for what's inside the
// array" as a contract.
final class BareLiteralInsideRuleSetFromRequest extends FormRequest
{
    public function rules(): RuleSet
    {
        return RuleSet::from(['*' => FluentRule::array()->children([
            'foo' => FluentRule::string()->required(),
            'bar' => FluentRule::integer()->min(0),
        ])]);
    }
}

?>

<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\RuleSet;

// 0.19.1 P0 main feature × wildcard-prefix concat fold (the 0.19.0
// headline shape): rules() returns RuleSet::from([…]) wrapper with
// '*.' . CONST siblings inside. Pre-0.19.1 the rector silent-bailed
// on the MethodCall return expr; post-0.19.1 it descends into the
// from() argument and folds the inner array. The wrapper itself
// stays intact (RuleSet::from(<folded array>)).
final class WildcardPrefixConstInsideRuleSetFromRequest extends FormRequest
{
    public const string NAME = 'name';

    public const string COLOR = 'color';

    public function rules(): RuleSet
    {
        return RuleSet::from([
            '*.' . self::NAME => FluentRule::string()->required(),
            '*.' . self::COLOR => FluentRule::string()->nullable(),
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

// 0.19.1 P0 main feature × wildcard-prefix concat fold (the 0.19.0
// headline shape): rules() returns RuleSet::from([…]) wrapper with
// '*.' . CONST siblings inside. Pre-0.19.1 the rector silent-bailed
// on the MethodCall return expr; post-0.19.1 it descends into the
// from() argument and folds the inner array. The wrapper itself
// stays intact (RuleSet::from(<folded array>)).
final class WildcardPrefixConstInsideRuleSetFromRequest extends FormRequest
{
    public const string NAME = 'name';

    public const string COLOR = 'color';

    public function rules(): RuleSet
    {
        return RuleSet::from(['*' => FluentRule::array()->children([
            self::NAME => FluentRule::string()->required(),
            self::COLOR => FluentRule::string()->nullable(),
        ])]);
    }
}

?>

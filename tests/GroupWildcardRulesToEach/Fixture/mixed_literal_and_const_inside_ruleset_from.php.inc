<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\RuleSet;

// 0.19.1 cross-fold-path matrix cell: the
// `mixed_literal_and_const_wildcard_keys` regression pin (0.19.0
// codex-find: stale-snapshot prevented two folds emitting the same
// '*' top-level key) lifted into the RuleSet::from() wrapper.
// Confirms the live-scan detector behaves identically inside the
// wrapper as outside — partial conversion, no rule loss, const
// branch bails-and-logs because the literal fold synthesized the
// '*' parent first.
final class MixedLiteralAndConstInsideRuleSetFromRequest extends FormRequest
{
    public const string BAR = 'bar';

    public function rules(): RuleSet
    {
        return RuleSet::from([
            '*.foo' => FluentRule::string()->required(),
            '*.' . self::BAR => FluentRule::integer()->min(0),
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

// 0.19.1 cross-fold-path matrix cell: the
// `mixed_literal_and_const_wildcard_keys` regression pin (0.19.0
// codex-find: stale-snapshot prevented two folds emitting the same
// '*' top-level key) lifted into the RuleSet::from() wrapper.
// Confirms the live-scan detector behaves identically inside the
// wrapper as outside — partial conversion, no rule loss, const
// branch bails-and-logs because the literal fold synthesized the
// '*' parent first.
final class MixedLiteralAndConstInsideRuleSetFromRequest extends FormRequest
{
    public const string BAR = 'bar';

    public function rules(): RuleSet
    {
        return RuleSet::from([
            '*' => FluentRule::array()->children([
                'foo' => FluentRule::string()->required(),
            ]),
            '*.' . self::BAR => FluentRule::integer()->min(0),
        ]);
    }
}

?>

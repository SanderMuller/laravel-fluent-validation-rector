<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentRules;
use SanderMuller\FluentValidation\RuleSet;

// rules() may return a RuleSet, not just a plain array. The rename + receiver
// swap apply regardless of return type; RuleSet::from() stays verbatim.
class ConvertRuleSetReturnShape extends FormRequest
{
    use HasFluentRules;

    public function rules(): RuleSet
    {
        return RuleSet::from([
            'name' => FluentRule::string()->required(),
        ])->only(['name']);
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentSchema;
use SanderMuller\FluentValidation\HasFluentRules;
use SanderMuller\FluentValidation\RuleSet;

// rules() may return a RuleSet, not just a plain array. The rename + receiver
// swap apply regardless of return type; RuleSet::from() stays verbatim.
class ConvertRuleSetReturnShape extends FormRequest
{
    use HasFluentRules;

    public function schema(FluentSchema $rules): RuleSet
    {
        return RuleSet::from([
            'name' => $rules->string()->required(),
        ])->only(['name']);
    }
}

?>

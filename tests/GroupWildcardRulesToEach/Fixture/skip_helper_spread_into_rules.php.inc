<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

// rules() spreads the helper's result into an array that also has a
// 'summary' key. Folding 'summary.heading' into 'summary' => array()->children()
// would make the spread replace rules()'s own 'summary' rule.
class SpreadHelperRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'summary' => FluentRule::array()->required(),
            ...$this->summaryRules(),
        ];
    }

    private function summaryRules(): array
    {
        return [
            'summary.heading' => FluentRule::string()->required()->max(100),
        ];
    }
}

?>

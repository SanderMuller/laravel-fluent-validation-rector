<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

// The spread can bring in a 'summary' key. Folding 'summary.heading' into a
// 'summary' key here would let the spread replace the folded rule.
class FoldNextToSpreadRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'summary.heading' => FluentRule::string()->required(),
            ...$this->sharedRules(),
        ];
    }

    public function sharedRules(): array
    {
        return [
            'summary' => FluentRule::array()->required(),
        ];
    }
}

?>

<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationStringToFluentRule\Fixture;

use Illuminate\Foundation\Http\FormRequest;

// Custom-named helper builds the rules array with a pre-return
// assignment. Auto-detection requires a single-`return-literal-Array_`
// shape (`isRulesShapedMethod`); multi-statement bodies bail. Stays
// untouched. Documented limitation — `$rules = [...]; return $rules;`
// is common enough but the constraint matches other rectors in this
// package (e.g. InlineResolvableParentRulesRector's strict mode).
class SkipMultiStatementHelper extends FormRequest
{
    public function rules(): array
    {
        return $this->buildRules();
    }

    private function buildRules(): array
    {
        $rules = [
            'title' => 'required|string|max:255',
        ];

        return $rules;
    }
}

?>

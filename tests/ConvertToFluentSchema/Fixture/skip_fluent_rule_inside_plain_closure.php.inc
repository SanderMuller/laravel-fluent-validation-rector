<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentRules;

// A FluentRule chain built inside a plain closure would not have the injected
// $rules builder in scope (a plain closure captures nothing without `use`).
// Refuse the conversion rather than emit an undefined-variable reference.
class SkipFluentRuleInsidePlainClosure extends FormRequest
{
    use HasFluentRules;

    public function rules(): array
    {
        $build = function () {
            return FluentRule::string()->required();
        };

        return [
            'name' => $build(),
        ];
    }
}

?>

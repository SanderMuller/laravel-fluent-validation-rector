<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentRules;

// A FluentRule chain built inside an anonymous class (its own scope) can't see
// the injected $rules builder — rewriting it would emit an undefined variable.
// Refuse the whole conversion, as with a plain closure.
class AnonClassRulesRequest extends FormRequest
{
    use HasFluentRules;

    public function rules(): array
    {
        return [
            'name' => FluentRule::string()->required(),
            'custom' => new class
            {
                public function build()
                {
                    return FluentRule::string()->required();
                }
            },
        ];
    }
}

?>

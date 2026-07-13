<?php

namespace SanderMuller\FluentValidationRector\Tests\AddHasFluentRulesTrait\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

// Abstract FormRequest with a literal rules() method using FluentRule but NO
// #[FluentRules] attribute. Subclasses may array-manipulate parent::rules(),
// so the trait must not be added to the base without the explicit per-method
// opt-in. Adding #[FluentRules] to rules() (or the class to base_classes
// config) lifts this guard.
abstract class AbstractUnattributedFormRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => FluentRule::string()->required(),
        ];
    }
}

?>

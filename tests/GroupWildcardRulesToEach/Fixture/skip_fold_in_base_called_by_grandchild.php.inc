<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

// The middle class does not override rules(), so the grandchild's
// parent::rules() returns the base's array.
class BaseOptionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'options.*' => FluentRule::string()->max(20),
        ];
    }
}

class MiddleOptionRequest extends BaseOptionRequest
{
}

class StoreOptionRequest extends MiddleOptionRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'options' => FluentRule::array()->required(),
        ];
    }
}

?>

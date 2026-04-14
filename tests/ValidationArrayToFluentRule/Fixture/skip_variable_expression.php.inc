<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use Illuminate\Foundation\Http\FormRequest;

class VariableExpressionFormRequest extends FormRequest
{
    public function rules(): array
    {
        $dynamicRule = 'max:255';

        return [
            'name' => ['required', 'string', $dynamicRule],
        ];
    }
}

?>

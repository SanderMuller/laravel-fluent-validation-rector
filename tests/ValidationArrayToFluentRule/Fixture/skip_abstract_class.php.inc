<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use Illuminate\Foundation\Http\FormRequest;

abstract class AbstractQuestionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'type' => ['required', 'string'],
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}

?>

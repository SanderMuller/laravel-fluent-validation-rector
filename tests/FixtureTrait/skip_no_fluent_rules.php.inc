<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureTrait;

use Illuminate\Foundation\Http\FormRequest;

class NativeRulesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}

?>

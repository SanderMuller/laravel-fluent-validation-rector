<?php

namespace SanderMuller\FluentValidationRector\Tests\UpdateRulesReturnTypeDocblock\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class SkipClosureAtValue extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => FluentRule::string()->required(),
            'custom' => fn (string $attr, mixed $value, \Closure $fail): mixed => $fail('nope'),
        ];
    }
}

?>

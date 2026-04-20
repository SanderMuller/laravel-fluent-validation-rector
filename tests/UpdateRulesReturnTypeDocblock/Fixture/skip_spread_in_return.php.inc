<?php

namespace SanderMuller\FluentValidationRector\Tests\UpdateRulesReturnTypeDocblock\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class SkipSpreadInReturn extends FormRequest
{
    public function rules(): array
    {
        $shared = ['body' => FluentRule::string()->nullable()];

        return [
            ...$shared,
            'title' => FluentRule::string()->required(),
        ];
    }
}

?>

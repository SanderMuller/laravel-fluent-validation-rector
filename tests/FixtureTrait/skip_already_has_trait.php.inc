<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureTrait;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentRules;

class AlreadyHasTraitRequest extends FormRequest
{
    use HasFluentRules;

    public function rules(): array
    {
        return [
            'name' => FluentRule::string()->required(),
        ];
    }
}

?>

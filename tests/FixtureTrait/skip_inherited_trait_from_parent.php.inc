<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureTrait;

use SanderMuller\FluentValidation\FluentFormRequest;
use SanderMuller\FluentValidation\FluentRule;

class SubclassOfFluentFormRequest extends FluentFormRequest
{
    public function rules(): array
    {
        return [
            'name' => FluentRule::string()->required(),
        ];
    }
}

?>

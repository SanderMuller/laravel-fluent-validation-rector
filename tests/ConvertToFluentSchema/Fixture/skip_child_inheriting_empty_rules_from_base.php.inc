<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Source\EmptyRulesBaseRequest;

// The base declares rules() returning [] and does not convert. Renaming this
// rules() to schema() would make $request->rules() resolve to the base's empty
// array, so a direct rules() caller would lose every rule.
class ChildInheritingEmptyRulesRequest extends EmptyRulesBaseRequest
{
    public function rules(): array
    {
        return [
            'title' => FluentRule::string()->required(),
        ];
    }
}

?>

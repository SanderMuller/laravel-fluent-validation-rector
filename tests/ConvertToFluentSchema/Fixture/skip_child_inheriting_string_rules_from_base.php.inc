<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Source\StringRulesBaseRequest;

// The child does not call parent::rules(), but the base keeps its own rules().
// After a rename, $request->rules() would return the base's rules instead of
// the child's.
class ChildInheritingStringRulesRequest extends StringRulesBaseRequest
{
    public function rules(): array
    {
        return [
            'body' => FluentRule::string()->required(),
        ];
    }
}

?>

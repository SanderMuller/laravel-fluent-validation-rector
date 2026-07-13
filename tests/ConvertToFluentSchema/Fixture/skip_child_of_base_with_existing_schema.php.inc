<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\RuleSet;
use SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Source\BaseWithSchemaRequest;

// The base (Source\BaseWithSchemaRequest) already declares schema() alongside
// rules(), so the converter bails it (a rename would duplicate schema()) and it
// keeps rules(). Rewriting this child's parent::rules() to parent::schema()
// would strand it, so the child is skipped — the parent-conversion probe sees
// the pre-existing schema() by reflection and returns false.
class ChildOfBaseWithSchemaRequest extends BaseWithSchemaRequest
{
    public function rules(): RuleSet
    {
        return parent::rules()->merge([
            'body' => FluentRule::string()->required(),
        ]);
    }
}

?>

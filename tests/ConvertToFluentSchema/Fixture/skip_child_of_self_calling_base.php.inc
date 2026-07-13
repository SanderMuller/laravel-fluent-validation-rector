<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\RuleSet;
use SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Source\SelfCallingBaseRequest;

// The base (Source\SelfCallingBaseRequest) has a sibling method that calls
// $this->rules(), so the converter bails it (hasSelfRulesCallOutsideMethod) and
// it keeps rules(). The probe mirrors that guard on the parsed parent, so this
// child is skipped rather than rewritten to a stranded parent::schema().
class ChildOfSelfCallingBase extends SelfCallingBaseRequest
{
    public function rules(): RuleSet
    {
        return parent::rules()->merge([
            'body' => FluentRule::string()->required(),
        ]);
    }
}

?>

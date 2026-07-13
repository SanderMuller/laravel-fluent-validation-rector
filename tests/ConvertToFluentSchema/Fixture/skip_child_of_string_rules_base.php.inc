<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Source\StringRulesBaseRequest;

// The base (Source\StringRulesBaseRequest) uses HasFluentRules but its rules()
// still returns plain string rules, so the converter bails it (nothing fluent
// to convert) and it keeps rules(). The parent-conversion probe parses the
// base's rules() body, finds no convertible content, and returns false — so the
// child is skipped rather than rewritten to a stranded parent::schema().
class ChildOfStringRulesBaseRequest extends StringRulesBaseRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'body' => FluentRule::string()->required(),
        ]);
    }
}

?>

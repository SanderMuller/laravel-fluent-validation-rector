<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineResolvableParentRules\Fixture;

use SanderMuller\FluentValidationRector\Tests\InlineResolvableParentRules\Source\BaseWithMultiStatementRules;

// Parent's rules() has more than a single Return_ of an Array_ literal —
// rector must leave the spread intact rather than risk partial inlining.
class SkipMultiStmtParent extends BaseWithMultiStatementRules
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'extra' => 'required|string',
        ];
    }
}

?>

<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineResolvableParentRules\Fixture;

use SanderMuller\FluentValidationRector\Tests\InlineResolvableParentRules\Source\BasePlayerSessionHistoryRequest;

// No spread — rules() array is already flat. Rector must not touch.
class SkipNoSpreadItem extends BasePlayerSessionHistoryRequest
{
    public function rules(): array
    {
        return [
            'action' => 'required|string',
        ];
    }
}

?>

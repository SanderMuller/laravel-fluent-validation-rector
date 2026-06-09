<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineResolvableParentRules\Fixture;

use SanderMuller\FluentValidationRector\Tests\InlineResolvableParentRules\Source\BaseArticleRequest;

// No spread — rules() array is already flat. Rector must not touch.
class SkipNoSpreadItem extends BaseArticleRequest
{
    public function rules(): array
    {
        return [
            'action' => 'required|string',
        ];
    }
}

?>

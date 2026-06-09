<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineResolvableParentRules\Fixture;

use SanderMuller\FluentValidationRector\Tests\InlineResolvableParentRules\Source\BaseArticleRequest;

class InlineParentRulesPlainLiteral extends BaseArticleRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'action' => 'required|string',
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineResolvableParentRules\Fixture;

use SanderMuller\FluentValidationRector\Tests\InlineResolvableParentRules\Source\BaseArticleRequest;

class InlineParentRulesPlainLiteral extends BaseArticleRequest
{
    public function rules(): array
    {
        return [
            'author_id' => 'required|string',
            'session_id' => 'required|string|uuid',
            'action' => 'required|string',
        ];
    }
}

?>

<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineResolvableParentRules\Fixture;

use SanderMuller\FluentValidationRector\Tests\InlineResolvableParentRules\Source\BaseArticleRequest;

final class InlineVariableSpreadFromParentRules extends BaseArticleRequest
{
    public function rules(): array
    {
        $base = parent::rules();

        return [
            ...$base,
            'action' => 'required|string',
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineResolvableParentRules\Fixture;

use SanderMuller\FluentValidationRector\Tests\InlineResolvableParentRules\Source\BaseArticleRequest;

final class InlineVariableSpreadFromParentRules extends BaseArticleRequest
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

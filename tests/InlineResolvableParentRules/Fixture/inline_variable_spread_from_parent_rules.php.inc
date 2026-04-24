<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineResolvableParentRules\Fixture;

use SanderMuller\FluentValidationRector\Tests\InlineResolvableParentRules\Source\BasePlayerSessionHistoryRequest;

final class InlineVariableSpreadFromParentRules extends BasePlayerSessionHistoryRequest
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

use SanderMuller\FluentValidationRector\Tests\InlineResolvableParentRules\Source\BasePlayerSessionHistoryRequest;

final class InlineVariableSpreadFromParentRules extends BasePlayerSessionHistoryRequest
{
    public function rules(): array
    {
        return [
            'player_id' => 'required|string',
            'session_id' => 'required|string|uuid',
            'action' => 'required|string',
        ];
    }
}

?>

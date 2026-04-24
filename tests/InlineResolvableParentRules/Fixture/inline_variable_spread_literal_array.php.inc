<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineResolvableParentRules\Fixture;

use Illuminate\Foundation\Http\FormRequest;

final class InlineVariableSpreadLiteralArray extends FormRequest
{
    public function rules(): array
    {
        $base = [
            'player_id' => 'required|string',
            'session_id' => 'required|string|uuid',
        ];

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

use Illuminate\Foundation\Http\FormRequest;

final class InlineVariableSpreadLiteralArray extends FormRequest
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

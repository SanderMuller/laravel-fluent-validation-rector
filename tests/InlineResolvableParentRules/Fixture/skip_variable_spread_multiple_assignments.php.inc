<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineResolvableParentRules\Fixture;

use Illuminate\Foundation\Http\FormRequest;

// Two top-level assignments to `$base` — can't statically pick the right literal
// without simulating the method body. Bail.
final class SkipVariableSpreadMultipleAssignments extends FormRequest
{
    public function rules(): array
    {
        $base = [
            'one' => 'required|string',
        ];

        $base = [
            'two' => 'required|string',
        ];

        return [
            ...$base,
            'tail' => 'required|string',
        ];
    }
}

?>

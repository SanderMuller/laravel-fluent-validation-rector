<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineResolvableParentRules\Fixture;

use Illuminate\Foundation\Http\FormRequest;

// Codex must-have: assignment inside an `if` block does NOT dominate the
// return — the branch may not execute. Inlining would fabricate parent
// rules on the else path. Bail via the top-level-stmt-filter: the `if` is
// not an `Expression(Assign)`, so `extractReturnArray` rejects the whole
// method shape.
final class SkipVariableSpreadAssignedInIfBlock extends FormRequest
{
    public function rules(): array
    {
        if ($this->has('feature')) {
            $base = ['one' => 'required|string'];
        }

        return [
            ...$base,
            'tail' => 'required|string',
        ];
    }
}

?>

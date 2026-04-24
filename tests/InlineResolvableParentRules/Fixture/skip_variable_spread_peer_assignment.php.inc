<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineResolvableParentRules\Fixture;

use Illuminate\Foundation\Http\FormRequest;

// Codex must-have: method body has a peer `$other = ...` statement
// alongside `$base = [...]`. Stripping the `$base` assign during inline
// would reorder `$other`'s side effects relative to the inlined items'
// RHS side effects. Gate: `countTopLevelAssignments !== 1` → bail.
final class SkipVariableSpreadPeerAssignment extends FormRequest
{
    public function rules(): array
    {
        $other = $this->input('limit');
        $base = [
            'quantity' => 'required|integer|max:' . $other,
        ];

        return [
            ...$base,
            'name' => 'required|string',
        ];
    }
}

?>

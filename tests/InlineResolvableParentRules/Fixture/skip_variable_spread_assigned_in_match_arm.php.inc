<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineResolvableParentRules\Fixture;

use Illuminate\Foundation\Http\FormRequest;

// Assignment via `match` arm — top-level Expression(Assign) where the RHS
// is a `Match_` expression. The assign IS top level, but the RHS is not a
// literal `Array_` so `resolveVariableSpread` bails (method-shape accepts
// it but RHS resolver rejects).
final class SkipVariableSpreadAssignedInMatchArm extends FormRequest
{
    public function rules(): array
    {
        $base = match ($this->input('variant')) {
            'a' => ['one' => 'required|string'],
            'b' => ['two' => 'required|string'],
            default => [],
        };

        return [
            ...$base,
            'tail' => 'required|string',
        ];
    }
}

?>

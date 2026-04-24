<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineResolvableParentRules\Fixture;

use Illuminate\Foundation\Http\FormRequest;

// Assignment inside a `foreach` loop body does not dominate the return
// (runs zero times for empty iterables). Top-level-stmt filter rejects
// the whole method.
final class SkipVariableSpreadAssignedInForeach extends FormRequest
{
    public function rules(): array
    {
        foreach ($this->defaults() as $defaults) {
            $base = $defaults;
        }

        return [
            ...$base,
            'tail' => 'required|string',
        ];
    }

    private function defaults(): array
    {
        return [['one' => 'required|string']];
    }
}

?>

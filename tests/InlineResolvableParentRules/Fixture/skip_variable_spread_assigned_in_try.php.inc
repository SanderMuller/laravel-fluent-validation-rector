<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineResolvableParentRules\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use Throwable;

// Assignment buried inside a `try` block: the catch path may run and leave
// `$base` unset. TryCatch is not an `Expression(Assign)`, so the method
// shape is rejected wholesale.
final class SkipVariableSpreadAssignedInTry extends FormRequest
{
    public function rules(): array
    {
        try {
            $base = ['one' => 'required|string'];
        } catch (Throwable) {
            // swallow
        }

        return [
            ...$base,
            'tail' => 'required|string',
        ];
    }
}

?>

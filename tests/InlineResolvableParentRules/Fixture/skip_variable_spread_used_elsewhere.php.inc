<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineResolvableParentRules\Fixture;

use Illuminate\Foundation\Http\FormRequest;

// `$base` is consumed by `array_merge` before the spread — inlining only
// the spread would drop the side-channel behavior. Bail.
final class SkipVariableSpreadUsedElsewhere extends FormRequest
{
    public function rules(): array
    {
        $base = [
            'one' => 'required|string',
        ];

        $merged = array_merge($base, ['middle' => 'nullable']);

        return [
            ...$base,
            'tail' => 'required|string',
        ];
    }
}

?>

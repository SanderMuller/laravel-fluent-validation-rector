<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineResolvableParentRules\Fixture;

use Illuminate\Foundation\Http\FormRequest;

// `$base` is captured by a closure `use ($base)`. That's a third
// reference on top of the write + spread read, tripping the
// count-references gate (> 2) and leaving the spread intact.
final class SkipVariableSpreadClosureCapture extends FormRequest
{
    public function rules(): array
    {
        $base = [
            'one' => 'required|string',
        ];

        $log = function () use ($base): void {
            // pretend we send $base somewhere
        };

        return [
            ...$base,
            'tail' => 'required|string',
        ];
    }
}

?>

<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use Illuminate\Foundation\Http\FormRequest;

class UnsafeTupleSpreadFormRequest extends FormRequest
{
    public function rules(): array
    {
        $all = [];
        $args = [];
        $fields = [];
        $tuple = ['required_if', 'role', 'admin'];

        return [
            // Category A all-args spread: $field is inside the spread — bail.
            'a' => [
                ['required_unless', ...$all],
                'string',
            ],
            // Category C (field + optional message): second arg is the
            // message parameter, not a value list — bail.
            'b' => [
                ['required_if_accepted', ...$args],
                'string',
            ],
            // Category D (single field): extra args would silently drop — bail.
            'c' => [
                ['exclude_with', ...$fields],
                'string',
            ],
            // Non-variadic modifier: spread can't be absorbed — bail.
            'd' => [
                'required',
                ['max', ...$args],
            ],
            // First-item spread: rule-name position can never be spread — bail.
            'e' => [
                [...$tuple],
                'string',
            ],
        ];
    }
}

?>

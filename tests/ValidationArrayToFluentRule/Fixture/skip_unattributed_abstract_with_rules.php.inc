<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use Illuminate\Foundation\Http\FormRequest;

// Abstract class with `rules()` (array form) and NO `#[FluentRules]`.
// The safety guard fires; the array converter does not walk the body.
abstract class UnattributedArrayAbstractRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}

?>

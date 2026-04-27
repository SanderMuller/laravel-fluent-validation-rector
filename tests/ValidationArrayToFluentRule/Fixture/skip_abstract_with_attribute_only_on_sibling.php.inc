<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRules;

// Critical regression fixture: abstract class with `rules()` (array
// form, NOT attributed) plus a `#[FluentRules]`-attributed sibling
// helper. The sibling attribute does NOT lift the guard for the
// unattributed `rules()` body.
abstract class ArrayAbstractWithSiblingAttributedHelper extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }

    #[FluentRules]
    public function helperMethod(): array
    {
        return [
            'helper_field' => ['required', 'string'],
        ];
    }
}

?>

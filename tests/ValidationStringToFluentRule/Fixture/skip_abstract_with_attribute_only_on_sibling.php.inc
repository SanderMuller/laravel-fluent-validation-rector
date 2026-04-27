<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationStringToFluentRule\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRules;

// Critical regression fixture: abstract class with `rules()` (NOT
// attributed) plus a `#[FluentRules]`-attributed sibling helper. The
// sibling attribute must NOT lift the guard for the unattributed
// `rules()` body — that would reopen the
// `array_merge(parent::rules(), [...])` hazard. The attributed
// helper itself does NOT convert here either, because the class
// fails the abstract-with-rules guard before per-method dispatch.
abstract class AbstractWithSiblingAttributedHelper extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
        ];
    }

    #[FluentRules]
    public function helperMethod(): array
    {
        return [
            'helper_field' => 'required|string',
        ];
    }
}

?>

<?php

namespace SanderMuller\FluentValidationRector\Tests\AddHasFluentRulesTrait\Fixture;

use Illuminate\Foundation\Http\FormRequest;

abstract class AjaxFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
}

?>

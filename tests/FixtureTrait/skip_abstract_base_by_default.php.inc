<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureTrait;

use Illuminate\Foundation\Http\FormRequest;

abstract class AjaxFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
}

?>

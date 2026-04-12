<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureArray;

use Illuminate\Foundation\Http\FormRequest;

class ArrayInsertsFluentRuleImportRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', ['max', 255]],
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureArray;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class ArrayInsertsFluentRuleImportRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => FluentRule::string()->required()->max(255),
        ];
    }
}

?>

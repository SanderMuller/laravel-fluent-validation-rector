<?php

namespace SanderMuller\FluentValidationRector\Tests\Fixture;

use Illuminate\Foundation\Http\FormRequest;

class InsertsFluentRuleImportRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class InsertsFluentRuleImportRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => FluentRule::string()->required()->max(255),
        ];
    }
}

?>

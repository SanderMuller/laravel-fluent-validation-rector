<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationStringToFluentRule\Fixture;

use Illuminate\Foundation\Http\FormRequest;

class AutoDetectEditorRules extends FormRequest
{
    public function rules(): array
    {
        return $this->editorRules();
    }

    public function editorRules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'body' => 'required|string',
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationStringToFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use Illuminate\Foundation\Http\FormRequest;

class AutoDetectEditorRules extends FormRequest
{
    public function rules(): array
    {
        return $this->editorRules();
    }

    public function editorRules(): array
    {
        return [
            'title' => FluentRule::string()->required()->max(255),
            'body' => FluentRule::string()->required(),
        ];
    }
}

?>

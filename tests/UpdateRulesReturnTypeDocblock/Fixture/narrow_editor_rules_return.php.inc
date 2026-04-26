<?php

namespace SanderMuller\FluentValidationRector\Tests\UpdateRulesReturnTypeDocblock\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class NarrowEditorRulesReturn extends FormRequest
{
    public function rules(): array
    {
        return $this->editorRules();
    }

    public function editorRules(): array
    {
        return [
            'title' => FluentRule::string()->required()->max(255),
            'body' => FluentRule::string()->nullable(),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\UpdateRulesReturnTypeDocblock\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class NarrowEditorRulesReturn extends FormRequest
{
    public function rules(): array
    {
        return $this->editorRules();
    }

    /**
     * @return array<string, \SanderMuller\FluentValidation\Contracts\FluentRuleContract>
     */
    public function editorRules(): array
    {
        return [
            'title' => FluentRule::string()->required()->max(255),
            'body' => FluentRule::string()->nullable(),
        ];
    }
}

?>

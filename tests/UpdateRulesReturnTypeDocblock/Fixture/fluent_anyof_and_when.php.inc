<?php

namespace SanderMuller\FluentValidationRector\Tests\UpdateRulesReturnTypeDocblock\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class FluentAnyofAndWhen extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => FluentRule::anyOf([FluentRule::string()->required(), FluentRule::numeric()->required()]),
            'body' => FluentRule::when(true, FluentRule::string()->required()),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\UpdateRulesReturnTypeDocblock\Fixture;

use SanderMuller\FluentValidation\Contracts\FluentRuleContract;
use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class FluentAnyofAndWhen extends FormRequest
{
    /**
     * @return array<string, FluentRuleContract>
     */
    public function rules(): array
    {
        return [
            'title' => FluentRule::anyOf([FluentRule::string()->required(), FluentRule::numeric()->required()]),
            'body' => FluentRule::when(true, FluentRule::string()->required()),
        ];
    }
}

?>

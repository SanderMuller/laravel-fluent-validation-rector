<?php

namespace SanderMuller\FluentValidationRector\Tests\UpdateRulesReturnTypeDocblock\Fixture;

use Illuminate\Foundation\Http\FormRequest as BaseRequest;
use SanderMuller\FluentValidation\FluentRule;

class ExtendsAliasedFormRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'title' => FluentRule::string()->required(),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\UpdateRulesReturnTypeDocblock\Fixture;

use SanderMuller\FluentValidation\Contracts\FluentRuleContract;
use Illuminate\Foundation\Http\FormRequest as BaseRequest;
use SanderMuller\FluentValidation\FluentRule;

class ExtendsAliasedFormRequest extends BaseRequest
{
    /**
     * @return array<string, FluentRuleContract>
     */
    public function rules(): array
    {
        return [
            'title' => FluentRule::string()->required(),
        ];
    }
}

?>

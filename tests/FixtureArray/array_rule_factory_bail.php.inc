<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureArray;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RuleFactoryBailFormRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'count' => ['required', Rule::numeric()->integer(true)],
            'data' => ['required', Rule::string()->unknownMethod()],
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureArray;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use SanderMuller\FluentValidation\FluentRule;

class RuleFactoryBailFormRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'count' => FluentRule::field()->required()->rule(Rule::numeric()->integer(true)),
            'data' => FluentRule::field()->required()->rule(Rule::string()->unknownMethod()),
        ];
    }
}

?>

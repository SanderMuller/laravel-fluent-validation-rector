<?php

namespace SanderMuller\FluentValidationRector\Tests\UpdateRulesReturnTypeDocblock\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\Rules\StringRule;

class FluentWhenWithTypedClosureReturn extends FormRequest
{
    public function rules(): array
    {
        return [
            'code' => FluentRule::string()->when(true, fn (StringRule $r): StringRule => $r->in(['A', 'B'])),
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
use SanderMuller\FluentValidation\Rules\StringRule;

class FluentWhenWithTypedClosureReturn extends FormRequest
{
    /**
     * @return array<string, FluentRuleContract>
     */
    public function rules(): array
    {
        return [
            'code' => FluentRule::string()->when(true, fn (StringRule $r): StringRule => $r->in(['A', 'B'])),
        ];
    }
}

?>

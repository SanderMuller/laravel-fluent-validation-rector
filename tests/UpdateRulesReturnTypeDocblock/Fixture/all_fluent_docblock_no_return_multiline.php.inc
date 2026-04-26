<?php

namespace SanderMuller\FluentValidationRector\Tests\UpdateRulesReturnTypeDocblock\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class AllFluentDocblockNoReturnMultiline extends FormRequest
{
    /**
     * Describes the store-post validation shape.
     */
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
use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class AllFluentDocblockNoReturnMultiline extends FormRequest
{
    /**
     * Describes the store-post validation shape.
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

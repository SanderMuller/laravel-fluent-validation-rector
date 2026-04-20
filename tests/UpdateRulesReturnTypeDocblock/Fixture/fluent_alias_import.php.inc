<?php

namespace SanderMuller\FluentValidationRector\Tests\UpdateRulesReturnTypeDocblock\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule as FR;

class FluentAliasImport extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => FR::string()->required(),
            'body' => FR::string()->nullable(),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\UpdateRulesReturnTypeDocblock\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule as FR;

class FluentAliasImport extends FormRequest
{
    /**
     * @return array<string, \SanderMuller\FluentValidation\Contracts\FluentRuleContract>
     */
    public function rules(): array
    {
        return [
            'title' => FR::string()->required(),
            'body' => FR::string()->nullable(),
        ];
    }
}

?>

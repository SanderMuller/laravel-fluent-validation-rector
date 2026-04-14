<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class MultiLevelRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'columns' => FluentRule::array()->required(),
            'columns.*.data' => FluentRule::field()->nullable(),
            'columns.*.data._' => FluentRule::string()->nullable(),
            'columns.*.data.sort' => FluentRule::string()->nullable(),
            'columns.*.search' => FluentRule::field()->nullable(),
            'columns.*.search.value' => FluentRule::string()->nullable(),
            'columns.*.search.regex' => FluentRule::boolean()->nullable(),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class MultiLevelRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'columns' => FluentRule::array()->required()->each([
                'data' => FluentRule::field()->nullable()->children([
                    '_' => FluentRule::string()->nullable(),
                    'sort' => FluentRule::string()->nullable(),
                ]),
                'search' => FluentRule::field()->nullable()->children([
                    'value' => FluentRule::string()->nullable(),
                    'regex' => FluentRule::boolean()->nullable(),
                ]),
            ]),
        ];
    }
}

?>

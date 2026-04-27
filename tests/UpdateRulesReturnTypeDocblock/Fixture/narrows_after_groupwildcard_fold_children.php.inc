<?php

namespace SanderMuller\FluentValidationRector\Tests\UpdateRulesReturnTypeDocblock\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class FoldedChildrenRequest extends FormRequest
{
    public const string NAME = 'name';

    public const string COLOR = 'color';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            '*' => FluentRule::array()->children([
                self::NAME => FluentRule::string()->required(),
                self::COLOR => FluentRule::string()->nullable(),
            ]),
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

class FoldedChildrenRequest extends FormRequest
{
    public const string NAME = 'name';

    public const string COLOR = 'color';

    /**
     * @return array<string, FluentRuleContract>
     */
    public function rules(): array
    {
        return [
            '*' => FluentRule::array()->children([
                self::NAME => FluentRule::string()->required(),
                self::COLOR => FluentRule::string()->nullable(),
            ]),
        ];
    }
}

?>

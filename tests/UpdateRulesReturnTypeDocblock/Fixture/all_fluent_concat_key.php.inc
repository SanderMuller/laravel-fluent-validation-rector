<?php

namespace SanderMuller\FluentValidationRector\Tests\UpdateRulesReturnTypeDocblock\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class AllFluentConcatKey extends FormRequest
{
    public const BASE_URI = 'base_uri';

    public function rules(): array
    {
        return [
            'credentials.' . self::BASE_URI => FluentRule::string()->required()->url(),
            'company.' . self::BASE_URI . '.nested' => FluentRule::string()->nullable(),
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

class AllFluentConcatKey extends FormRequest
{
    public const BASE_URI = 'base_uri';

    /**
     * @return array<string, FluentRuleContract>
     */
    public function rules(): array
    {
        return [
            'credentials.' . self::BASE_URI => FluentRule::string()->required()->url(),
            'company.' . self::BASE_URI . '.nested' => FluentRule::string()->nullable(),
        ];
    }
}

?>

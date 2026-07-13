<?php

namespace SanderMuller\FluentValidationRector\Tests\UpdateRulesReturnTypeDocblock\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentSchema;
use SanderMuller\FluentValidation\HasFluentRules;

class NarrowSchemaMethodReturn extends FormRequest
{
    use HasFluentRules;

    public function schema(FluentSchema $rules): array
    {
        return [
            'title' => $rules->string()->required(),
            'email' => $rules->email()->required(),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\UpdateRulesReturnTypeDocblock\Fixture;

use SanderMuller\FluentValidation\Contracts\FluentRuleContract;
use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentSchema;
use SanderMuller\FluentValidation\HasFluentRules;

class NarrowSchemaMethodReturn extends FormRequest
{
    use HasFluentRules;

    /**
     * @return array<string, FluentRuleContract>
     */
    public function schema(FluentSchema $rules): array
    {
        return [
            'title' => $rules->string()->required(),
            'email' => $rules->email()->required(),
        ];
    }
}

?>

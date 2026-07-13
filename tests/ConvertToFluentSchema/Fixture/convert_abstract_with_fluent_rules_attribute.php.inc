<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\FluentRules;
use SanderMuller\FluentValidation\HasFluentRules;

// #[FluentRules] on an abstract base's rules() is the audit assertion that
// subclasses don't call parent::rules() or drop base keys — with the ^1.32
// schema()/rules() merge handling overrides, the rename to schema() is safe.
abstract class AbstractAuditedRequest extends FormRequest
{
    use HasFluentRules;

    #[FluentRules]
    public function rules(): array
    {
        return [
            'name' => FluentRule::string()->required(),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRules;
use SanderMuller\FluentValidation\FluentSchema;
use SanderMuller\FluentValidation\HasFluentRules;

// #[FluentRules] on an abstract base's rules() is the audit assertion that
// subclasses don't call parent::rules() or drop base keys — with the ^1.32
// schema()/rules() merge handling overrides, the rename to schema() is safe.
abstract class AbstractAuditedRequest extends FormRequest
{
    use HasFluentRules;

    #[FluentRules]
    public function schema(FluentSchema $rules): array
    {
        return [
            'name' => $rules->string()->required(),
        ];
    }
}

?>

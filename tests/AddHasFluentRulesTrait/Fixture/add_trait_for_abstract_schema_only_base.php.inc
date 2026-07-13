<?php

namespace SanderMuller\FluentValidationRector\Tests\AddHasFluentRulesTrait\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentSchema;

// A schema()-only abstract base has no rules() for a subclass to manipulate,
// and WITHOUT the trait its schema() builder is never dispatched (validation
// silently disabled). So it receives HasFluentRules — subclasses inherit it —
// the same treatment a concrete schema()-only FormRequest gets. The
// abstract-with-rules() guard does not apply here (no literal rules()).
abstract class AbstractSchemaOnlyRequest extends FormRequest
{
    public function schema(FluentSchema $rules): array
    {
        return [
            'name' => $rules->string()->required(),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\AddHasFluentRulesTrait\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentSchema;
use SanderMuller\FluentValidation\HasFluentRules;

// A schema()-only abstract base has no rules() for a subclass to manipulate,
// and WITHOUT the trait its schema() builder is never dispatched (validation
// silently disabled). So it receives HasFluentRules — subclasses inherit it —
// the same treatment a concrete schema()-only FormRequest gets. The
// abstract-with-rules() guard does not apply here (no literal rules()).
abstract class AbstractSchemaOnlyRequest extends FormRequest
{
    use HasFluentRules;

    public function schema(FluentSchema $rules): array
    {
        return [
            'name' => $rules->string()->required(),
        ];
    }
}

?>

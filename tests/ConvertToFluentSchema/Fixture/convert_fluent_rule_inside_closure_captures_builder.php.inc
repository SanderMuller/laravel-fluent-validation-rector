<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentRules;
use SanderMuller\FluentValidation\RuleSet;

// rules() builds a rule with FluentRule:: INSIDE a non-capturing closure (the
// ->when() callback). The converter rewrites those receivers onto the injected
// builder and gives the closure a `use ($schema)` capture so the builder is in
// scope there. The builder is named $schema, not $rules, because the closure
// already binds $rules — resolveBuilderParamName() picks a non-clashing name.
class StoreWidgetRequest extends FormRequest
{
    use HasFluentRules;

    public function rules(): RuleSet
    {
        return RuleSet::from([
            'name' => FluentRule::string()->required(),
        ])->when($this->has('size'), function (RuleSet $rules): RuleSet {
            return $rules->put('size', FluentRule::integer()->nullable());
        });
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentSchema;
use SanderMuller\FluentValidation\HasFluentRules;
use SanderMuller\FluentValidation\RuleSet;

// rules() builds a rule with FluentRule:: INSIDE a non-capturing closure (the
// ->when() callback). The converter rewrites those receivers onto the injected
// builder and gives the closure a `use ($schema)` capture so the builder is in
// scope there. The builder is named $schema, not $rules, because the closure
// already binds $rules — resolveBuilderParamName() picks a non-clashing name.
class StoreWidgetRequest extends FormRequest
{
    use HasFluentRules;

    public function schema(FluentSchema $schema): RuleSet
    {
        return RuleSet::from([
            'name' => $schema->string()->required(),
        ])->when($this->has('size'), function (RuleSet $rules) use ($schema): RuleSet {
            return $rules->put('size', $schema->integer()->nullable());
        });
    }
}

?>

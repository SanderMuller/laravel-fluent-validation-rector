<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\RuleSet;
use SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Source\ConcreteBaseRequest;

// parent::rules()->put()->when() with a nested RuleSet closure. The closure
// parameter is named $rules, so the injected builder falls back to $schema
// (collision avoidance). FluentRule:: inside the arrow function is captured and
// rewritten to $schema->; the closure's own $rules->put(...) (a RuleSet method,
// not the class rules()) and the $this->has(...) condition are left untouched.
class ConditionalChildRequest extends ConcreteBaseRequest
{
    public function rules(): RuleSet
    {
        return parent::rules()
            ->put('field_a', FluentRule::string()->nullable())
            ->when(
                value: $this->has('field_b'),
                callback: fn (RuleSet $rules): RuleSet => $rules->put(field: 'field_b', rule: FluentRule::integer()->nullable()),
            );
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Fixture;

use SanderMuller\FluentValidation\FluentSchema;
use SanderMuller\FluentValidation\RuleSet;
use SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Source\ConcreteBaseRequest;

// parent::rules()->put()->when() with a nested RuleSet closure. The closure
// parameter is named $rules, so the injected builder falls back to $schema
// (collision avoidance). FluentRule:: inside the arrow function is captured and
// rewritten to $schema->; the closure's own $rules->put(...) (a RuleSet method,
// not the class rules()) and the $this->has(...) condition are left untouched.
class ConditionalChildRequest extends ConcreteBaseRequest
{
    public function schema(FluentSchema $schema): RuleSet
    {
        return parent::schema($schema)
            ->put('field_a', $schema->string()->nullable())
            ->when(
                value: $this->has('field_b'),
                callback: fn (RuleSet $rules): RuleSet => $rules->put(field: 'field_b', rule: $schema->integer()->nullable()),
            );
    }
}

?>

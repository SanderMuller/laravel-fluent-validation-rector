<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\RuleSet;
use SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Source\RulesOverridingChildOfSchemaBase;

// The base (Source\RulesOverridingChildOfSchemaBase) only INHERITS schema()
// from its own ancestor; it declares its own convertible rules(), which the
// converter renames to schema() (its Class_ declares no schema()). So this
// child's parent::rules() must be rewritten to parent::schema() — a
// declared-only schema() check (not ReflectionClass::hasMethod's inherited
// view) is what keeps this from being falsely skipped.
class ConvertChildOfInheritedSchemaBase extends RulesOverridingChildOfSchemaBase
{
    public function rules(): RuleSet
    {
        return parent::rules()->merge([
            'body' => FluentRule::string()->required(),
        ]);
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Fixture;

use SanderMuller\FluentValidation\FluentSchema;
use SanderMuller\FluentValidation\RuleSet;
use SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Source\RulesOverridingChildOfSchemaBase;

// The base (Source\RulesOverridingChildOfSchemaBase) only INHERITS schema()
// from its own ancestor; it declares its own convertible rules(), which the
// converter renames to schema() (its Class_ declares no schema()). So this
// child's parent::rules() must be rewritten to parent::schema() — a
// declared-only schema() check (not ReflectionClass::hasMethod's inherited
// view) is what keeps this from being falsely skipped.
class ConvertChildOfInheritedSchemaBase extends RulesOverridingChildOfSchemaBase
{
    public function schema(FluentSchema $rules): RuleSet
    {
        return parent::schema($rules)->merge([
            'body' => $rules->string()->required(),
        ]);
    }
}

?>

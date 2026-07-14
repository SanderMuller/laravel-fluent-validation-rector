<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\RuleSet;
use SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Source\SchemaOverConcreteRulesRequest;

// The base (Source\SchemaOverConcreteRulesRequest) declares schema(FluentSchema)
// but INHERITS rules() from ConcreteBaseRequest — so it is NOT schema()-only, and
// the schema-only shortcut declines it. It falls through to the rules()-chain
// walk, which proves ConcreteBaseRequest (the inherited rules() owner) converts,
// so the child rewrites parent::rules() → parent::schema() (which binds to the
// base's schema()). Had that owner NOT converted, the walk would leave
// parent::rules() intact instead (it still resolves to the inherited method) —
// so the rewrite never strands and never fires when the call would still resolve.
class ChildOfSchemaBaseInheritingConvertibleRules extends SchemaOverConcreteRulesRequest
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
use SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Source\SchemaOverConcreteRulesRequest;

// The base (Source\SchemaOverConcreteRulesRequest) declares schema(FluentSchema)
// but INHERITS rules() from ConcreteBaseRequest — so it is NOT schema()-only, and
// the schema-only shortcut declines it. It falls through to the rules()-chain
// walk, which proves ConcreteBaseRequest (the inherited rules() owner) converts,
// so the child rewrites parent::rules() → parent::schema() (which binds to the
// base's schema()). Had that owner NOT converted, the walk would leave
// parent::rules() intact instead (it still resolves to the inherited method) —
// so the rewrite never strands and never fires when the call would still resolve.
class ChildOfSchemaBaseInheritingConvertibleRules extends SchemaOverConcreteRulesRequest
{
    public function schema(FluentSchema $rules): RuleSet
    {
        return parent::schema($rules)->merge([
            'body' => $rules->string()->required(),
        ]);
    }
}

?>

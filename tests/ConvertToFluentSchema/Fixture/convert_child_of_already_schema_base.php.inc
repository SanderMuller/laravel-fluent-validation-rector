<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\RuleSet;
use SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Source\SchemaOnlyBaseRequest;

// The base (Source\SchemaOnlyBaseRequest) was already converted in an earlier
// pass: it declares schema(FluentSchema) and no rules(). parent::rules() no
// longer resolves on it, so this child's parent::rules() MUST be rewritten to
// parent::schema() (leaving it would strand). The rules()-declarer probe walk
// strides past a schema()-only base; nearestAncestorIsSchemaOnlyBuilder() sees
// it and proves the conversion.
class ChildOfSchemaOnlyBase extends SchemaOnlyBaseRequest
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
use SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Source\SchemaOnlyBaseRequest;

// The base (Source\SchemaOnlyBaseRequest) was already converted in an earlier
// pass: it declares schema(FluentSchema) and no rules(). parent::rules() no
// longer resolves on it, so this child's parent::rules() MUST be rewritten to
// parent::schema() (leaving it would strand). The rules()-declarer probe walk
// strides past a schema()-only base; nearestAncestorIsSchemaOnlyBuilder() sees
// it and proves the conversion.
class ChildOfSchemaOnlyBase extends SchemaOnlyBaseRequest
{
    public function schema(FluentSchema $rules): RuleSet
    {
        return parent::schema($rules)->merge([
            'body' => $rules->string()->required(),
        ]);
    }
}

?>

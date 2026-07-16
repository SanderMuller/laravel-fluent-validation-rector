<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\RuleSet;
use SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Source\SchemaOnlyBaseRequest;

// The base (Source\SchemaOnlyBaseRequest) was already converted in an earlier
// pass: it declares schema(FluentSchema) and no rules() of its own. This child's
// parent::rules() MUST be rewritten to parent::schema() (leaving it would strand
// against a base that no longer declares rules()). The rules()-declarer probe
// walk strides past a schema()-only base; parentIsSchemaOnlyBuilder() sees it and
// proves the conversion.
//
// This also guards the laravel-fluent-validation 1.33 fallback: from 1.33 the
// HasFluentRules trait ships a rules() that re-exposes schema(), so the base
// reflectively "has" a rules() method — but it's the trait fallback, not a real
// declaration, so parentIsSchemaOnlyBuilder() discounts it (by declaring file)
// and still recognizes the schema-only base. Green on both 1.32 (no fallback)
// and 1.33 (fallback present).
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
// pass: it declares schema(FluentSchema) and no rules() of its own. This child's
// parent::rules() MUST be rewritten to parent::schema() (leaving it would strand
// against a base that no longer declares rules()). The rules()-declarer probe
// walk strides past a schema()-only base; parentIsSchemaOnlyBuilder() sees it and
// proves the conversion.
//
// This also guards the laravel-fluent-validation 1.33 fallback: from 1.33 the
// HasFluentRules trait ships a rules() that re-exposes schema(), so the base
// reflectively "has" a rules() method — but it's the trait fallback, not a real
// declaration, so parentIsSchemaOnlyBuilder() discounts it (by declaring file)
// and still recognizes the schema-only base. Green on both 1.32 (no fallback)
// and 1.33 (fallback present).
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

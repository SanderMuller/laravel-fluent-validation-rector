<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Fixture;

use SanderMuller\FluentValidation\RuleSet;
use SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Source\SchemaOnlyBaseRequest;

// A child whose rules() only delegates to parent::rules(). Once the base is a
// schema()-only builder, the child must convert too, or parent::rules() is
// stranded on laravel-fluent-validation versions without the trait fallback.

class ChildPurelyDelegatingToSchemaBase extends SchemaOnlyBaseRequest
{
    public function rules(): RuleSet
    {
        return parent::rules();
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Fixture;

use SanderMuller\FluentValidation\FluentSchema;
use SanderMuller\FluentValidation\RuleSet;
use SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Source\SchemaOnlyBaseRequest;

// A child whose rules() only delegates to parent::rules(). Once the base is a
// schema()-only builder, the child must convert too, or parent::rules() is
// stranded on laravel-fluent-validation versions without the trait fallback.

class ChildPurelyDelegatingToSchemaBase extends SchemaOnlyBaseRequest
{
    public function schema(FluentSchema $rules): RuleSet
    {
        return parent::schema($rules);
    }
}

?>

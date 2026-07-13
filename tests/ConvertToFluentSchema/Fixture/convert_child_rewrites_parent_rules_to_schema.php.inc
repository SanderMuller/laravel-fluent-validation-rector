<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\RuleSet;
use SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Source\ConcreteBaseRequest;

// The concrete base (Source\ConcreteBaseRequest) converts to schema(), so this
// child's parent::rules() must be rewritten to parent::schema($rules) — else it
// strands after the rename. The base is a real autoloadable class, so the rule
// resolves by reflection that it converts. Mirrors the concrete-base
// parent::rules()->merge([...]) chains that regressed in 1.9.0.
class StoreChildRequest extends ConcreteBaseRequest
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
use SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Source\ConcreteBaseRequest;

// The concrete base (Source\ConcreteBaseRequest) converts to schema(), so this
// child's parent::rules() must be rewritten to parent::schema($rules) — else it
// strands after the rename. The base is a real autoloadable class, so the rule
// resolves by reflection that it converts. Mirrors the concrete-base
// parent::rules()->merge([...]) chains that regressed in 1.9.0.
class StoreChildRequest extends ConcreteBaseRequest
{
    public function schema(FluentSchema $rules): RuleSet
    {
        return parent::schema($rules)->merge([
            'body' => $rules->string()->required(),
        ]);
    }
}

?>

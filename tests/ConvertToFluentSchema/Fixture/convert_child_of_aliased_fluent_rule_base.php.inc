<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\RuleSet;
use SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Source\AliasedFluentRuleBaseRequest;

// The base (Source\AliasedFluentRuleBaseRequest) imports FluentRule under an
// alias (`use ...FluentRule as Rule`). The probe name-resolves the parsed base
// body, so the aliased Rule:: factory is recognized as FluentRule and the base
// reads as converting — this child's parent::rules() is rewritten to
// parent::schema(). A raw (un-resolved) parse would miss the alias and wrongly
// skip this child.
class ChildOfAliasedFluentRuleBase extends AliasedFluentRuleBaseRequest
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
use SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Source\AliasedFluentRuleBaseRequest;

// The base (Source\AliasedFluentRuleBaseRequest) imports FluentRule under an
// alias (`use ...FluentRule as Rule`). The probe name-resolves the parsed base
// body, so the aliased Rule:: factory is recognized as FluentRule and the base
// reads as converting — this child's parent::rules() is rewritten to
// parent::schema(). A raw (un-resolved) parse would miss the alias and wrongly
// skip this child.
class ChildOfAliasedFluentRuleBase extends AliasedFluentRuleBaseRequest
{
    public function schema(FluentSchema $rules): RuleSet
    {
        return parent::schema($rules)->merge([
            'body' => $rules->string()->required(),
        ]);
    }
}

?>

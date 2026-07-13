<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Fixture;

use SanderMuller\FluentValidation\RuleSet;
use SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Source\ConcreteBaseRequest;

// The child only MODIFIES the parent's rules — it has no FluentRule static of
// its own. It must still convert: leaving it as rules() while the concrete base
// converts to schema() would strand parent::rules(). The parent::rules() call
// is the convertible content here.
class ModifyChildRequest extends ConcreteBaseRequest
{
    public function rules(): RuleSet
    {
        return parent::rules()->modify('title', fn ($rule) => $rule->required());
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Fixture;

use SanderMuller\FluentValidation\FluentSchema;
use SanderMuller\FluentValidation\RuleSet;
use SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Source\ConcreteBaseRequest;

// The child only MODIFIES the parent's rules — it has no FluentRule static of
// its own. It must still convert: leaving it as rules() while the concrete base
// converts to schema() would strand parent::rules(). The parent::rules() call
// is the convertible content here.
class ModifyChildRequest extends ConcreteBaseRequest
{
    public function schema(FluentSchema $rules): RuleSet
    {
        return parent::schema($rules)->modify('title', fn ($rule) => $rule->required());
    }
}

?>

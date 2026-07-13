<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\RuleSet;
use SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Source\AbstractBaseRequest;

// The base (Source\AbstractBaseRequest) is abstract without #[FluentRules], so
// the converter holds it back — it keeps rules() at runtime. Rewriting this
// child's parent::rules() to parent::schema() would strand it, so the child is
// skipped and left as rules() (parent::rules() keeps resolving to the base).
class ChildOfAbstractBaseRequest extends AbstractBaseRequest
{
    public function rules(): RuleSet
    {
        return parent::rules()->merge([
            'body' => FluentRule::string()->required(),
        ]);
    }
}

?>

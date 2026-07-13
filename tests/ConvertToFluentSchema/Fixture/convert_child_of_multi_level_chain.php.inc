<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\RuleSet;
use SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Source\ChainMiddleRequest;

// 3-level chain: this child extends ChainMiddleRequest, which calls
// parent::rules() into the convertible ConcreteBaseRequest root. The transitive
// walk climbs child → ChainMiddleRequest (calls parent::rules(), so defers) →
// ConcreteBaseRequest (a convertible root), proving the whole chain converts —
// so this child's parent::rules() is rewritten to parent::schema($rules).
class ConvertChildOfMultiLevelChain extends ChainMiddleRequest
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
use SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Source\ChainMiddleRequest;

// 3-level chain: this child extends ChainMiddleRequest, which calls
// parent::rules() into the convertible ConcreteBaseRequest root. The transitive
// walk climbs child → ChainMiddleRequest (calls parent::rules(), so defers) →
// ConcreteBaseRequest (a convertible root), proving the whole chain converts —
// so this child's parent::rules() is rewritten to parent::schema($rules).
class ConvertChildOfMultiLevelChain extends ChainMiddleRequest
{
    public function schema(FluentSchema $rules): RuleSet
    {
        return parent::schema($rules)->merge([
            'body' => $rules->string()->required(),
        ]);
    }
}

?>

<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Source;

use Override;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\RuleSet;

/**
 * Middle of a 3-level chain: extends the convertible ConcreteBaseRequest root
 * and itself calls parent::rules(). It converts only because its root converts
 * — the transitive parent-conversion walk must climb from a child THROUGH this
 * level up to ConcreteBaseRequest before proving the whole chain converts.
 */
class ChainMiddleRequest extends ConcreteBaseRequest
{
    #[Override]
    public function rules(): RuleSet
    {
        return parent::rules()->merge([
            'summary' => FluentRule::string()->nullable(),
        ]);
    }
}

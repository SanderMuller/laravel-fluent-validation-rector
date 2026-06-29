<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use Illuminate\Validation\Rule;
use SanderMuller\FluentValidation\FluentRule;

class RequiredIfFacadeNegatedMethodCondition
{
    public function rules(): array
    {
        // `Rule::requiredIf(<negated method call>)` — the condition is a
        // `BooleanNot` of a method call, provably boolean and provably not a
        // literal null, so it folds into the dedicated `->requiredIf(...)`
        // method verbatim (arity 1, no named arg). Mirrors a downstream
        // consumer's `Rule::requiredIf(! $this->container->isSubContainer())`
        // shape.
        return [
            'enabled' => FluentRule::boolean()->bail()->rule(Rule::requiredIf(! $this->isSubContainer()))->nullable(),
        ];
    }

    private function isSubContainer(): bool
    {
        return false;
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class RequiredIfFacadeNegatedMethodCondition
{
    public function rules(): array
    {
        // `Rule::requiredIf(<negated method call>)` — the condition is a
        // `BooleanNot` of a method call, provably boolean and provably not a
        // literal null, so it folds into the dedicated `->requiredIf(...)`
        // method verbatim (arity 1, no named arg). Mirrors a downstream
        // consumer's `Rule::requiredIf(! $this->container->isSubContainer())`
        // shape.
        return [
            'enabled' => FluentRule::boolean()->bail()
                ->requiredIf(!$this->isSubContainer())->nullable(),
        ];
    }

    private function isSubContainer(): bool
    {
        return false;
    }
}

?>

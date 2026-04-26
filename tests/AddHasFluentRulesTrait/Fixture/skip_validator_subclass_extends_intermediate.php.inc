<?php

namespace SanderMuller\FluentValidationRector\Tests\AddHasFluentRulesTrait\Fixture;

use SanderMuller\FluentValidation\FluentRule;

// Stand-in for `JsonImportValidator extends FluentValidator extends Validator`
// — a custom validator subclass whose fluent functionality comes from
// the parent chain, not from `HasFluentRules`. The trait overrides
// `createDefaultValidator(ValidationFactory)`, a FormRequest-internal
// hook that doesn't apply to direct Validator subclasses either.
// Hihaho dogfood (2026-04-26) found 5 such JSON-import validator
// subclasses got the trait inserted. None extend FormRequest, so they
// must be skipped under the FormRequest-ancestry gate.
abstract class JsonImportValidator
{
    abstract protected function rulesWithoutPrefix(): array;
}

final class JsonInteractionImportValidator extends JsonImportValidator
{
    protected function rulesWithoutPrefix(): array
    {
        return [
            'type' => FluentRule::string()->required(),
            'duration' => FluentRule::integer()->required()->min(0),
        ];
    }
}

?>

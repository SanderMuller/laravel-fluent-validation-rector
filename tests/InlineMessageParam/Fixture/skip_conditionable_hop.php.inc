<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineMessageParam\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class SkipConditionableHop
{
    public function rules(bool $strict): array
    {
        // Conditionable proxies (when/unless/whenEmpty/unlessEmpty/whenInput)
        // can mutate outer $lastConstraint via closures. Chain stays.
        return [
            'a' => FluentRule::email()->when($strict, fn ($r) => $r->required())->message('x'),
            'b' => FluentRule::string()->unless($strict, fn ($r) => $r->nullable())->message('y'),
            'c' => FluentRule::numeric()->whenInput('mode', 'tight')->message('z'),
        ];
    }
}

?>

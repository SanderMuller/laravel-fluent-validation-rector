<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use Illuminate\Validation\Rule;
use SanderMuller\FluentValidation\FluentRule;

enum Status: string
{
    case Active = 'active';
}

class SkipEnumOnDisallowedReceiver
{
    public function rules(): array
    {
        // enum() is on HasEmbeddedRules — String / Numeric / Email /
        // Date / Field. NOT on Array / Boolean / File / Password /
        // Image. Receiver-type allowlist (reflection) catches these.
        return [
            'tags' => FluentRule::array()->rule(Rule::enum(Status::class)),
            'flag' => FluentRule::boolean()->rule(Rule::enum(Status::class)),
            'file' => FluentRule::file()->rule(Rule::enum(Status::class)),
        ];
    }
}

?>

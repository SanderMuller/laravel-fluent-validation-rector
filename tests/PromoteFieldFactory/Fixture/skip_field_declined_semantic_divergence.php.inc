<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use SanderMuller\FluentValidation\FluentRule;

// Sibling of `skip_field_accepted_semantic_divergence`: Laravel's
// `declined` rule accepts `"no"`, `"off"`, `"false"`, `0`, `"0"`,
// `false`. BooleanRule seeds `'boolean'` which rejects `"no"`, `"off"`,
// `"false"`. Same semantic-divergence class as `accepted`; same guard.
class SkipFieldDeclinedSemanticDivergence
{
    public function rules(): array
    {
        return [
            'opted_out' => FluentRule::field()->required()->rule('declined'),
        ];
    }
}

?>

<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use SanderMuller\FluentValidation\FluentRule;

// Promoting would move 'string' in front of 'max:5' in the compiled rule list.
class SkipFieldTypeTokenAfterConstraint
{
    public function rules(): array
    {
        return [
            'title' => FluentRule::field()->rule('max:5')->rule('string'),
        ];
    }
}

?>

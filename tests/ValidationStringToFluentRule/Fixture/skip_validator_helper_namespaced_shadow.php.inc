<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationStringToFluentRule\Fixture;

// `validator()` without leading `\` in a non-empty namespace —
// PhpParser's name resolver returns `SanderMuller\...\validator`, not
// the global `validator`. Conservative resolution bails: we can't
// statically prove no userland shadow. Documents the limitation.
class SkipValidatorHelperNamespacedShadow
{
    public function handle(array $input): void
    {
        validator(
            $input,
            [
                'email' => 'required|email|max:255',
            ],
        );
    }
}

?>

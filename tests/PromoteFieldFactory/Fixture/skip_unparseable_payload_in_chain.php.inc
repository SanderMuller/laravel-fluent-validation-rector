<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use Illuminate\Validation\Rules\Password;
use SanderMuller\FluentValidation\FluentRule;

class SkipUnparseablePayloadInChain
{
    public function rules(): array
    {
        return [
            // One of the ->rule() calls is a StaticCall Password::default() — non-v1
            // shape. Without a strong signal on that hop the intersection lacks a
            // vote, so we bail the whole chain rather than guess.
            'pwd' => FluentRule::field()->rule('max:64')->rule(Password::default()),
        ];
    }
}

?>

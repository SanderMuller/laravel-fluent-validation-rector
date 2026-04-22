<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineMessageParam\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class MessageForCollapseMultiRule
{
    public function rules(): array
    {
        // Adjacent messageFor collapse: each messageFor immediately follows
        // the method it's messaging. The rector rewrites each pair
        // independently — non-adjacent messageFor (e.g. ->min()->max()
        // ->messageFor('min', ...)) leaves the chain untouched because
        // the receiver method doesn't match the key.
        return [
            'name' => FluentRule::string()->required()->messageFor('required', 'Required.')->min(2)->messageFor('min', 'Too short.')->max(255),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineMessageParam\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class MessageForCollapseMultiRule
{
    public function rules(): array
    {
        // Adjacent messageFor collapse: each messageFor immediately follows
        // the method it's messaging. The rector rewrites each pair
        // independently — non-adjacent messageFor (e.g. ->min()->max()
        // ->messageFor('min', ...)) leaves the chain untouched because
        // the receiver method doesn't match the key.
        return [
            'name' => FluentRule::string()->required(message: 'Required.')->min(2, message: 'Too short.')->max(255),
        ];
    }
}

?>

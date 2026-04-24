<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineResolvableParentRules\Fixture;

use SanderMuller\FluentValidationRector\Tests\InlineResolvableParentRules\Source\BaseWithSelfConstKey;

// Parent's rules() array uses `self::FIELD_ID` as a key. Inlining would
// rebind `self::` to this child class, changing (or fataling) the key
// resolution. Rector must bail even though parent's rules() is a plain
// Return_ of an Array_ literal.
class SkipSelfConstKeyReference extends BaseWithSelfConstKey
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'extra' => 'required|string',
        ];
    }
}

?>

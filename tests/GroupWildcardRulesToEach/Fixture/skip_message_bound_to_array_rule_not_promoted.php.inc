<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class SkipMessageBoundToArrayRuleNotPromoted extends \Illuminate\Foundation\Http\FormRequest
{
    private const string ITEMS = 'items';

    public function rules(): array
    {
        // `message()` binds to the most-recently-added rule — here the bare
        // array rule. Stripping that rule during promotion would silently
        // retarget the message to `nullable`, so the fold stays an escape
        // hatch.
        return [
            self::ITEMS => FluentRule::field()
                ->nullable()
                ->rule('array')
                ->message('Must be an array'),
            self::ITEMS . '.*' => FluentRule::string()
                ->max(255),
        ];
    }
}

?>

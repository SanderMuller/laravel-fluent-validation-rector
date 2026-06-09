<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class SkipLabeledFieldArrayParentNotPromoted extends FormRequest
{
    private const string ITEMS = 'items';

    public function rules(): array
    {
        // `FluentRule::array()`'s first parameter is `$keys`, not `$label`, so a
        // labeled `field('…')` parent can't be re-rooted to `array()` without
        // mis-binding the label. The fold stays an escape hatch.
        return [
            self::ITEMS => FluentRule::field('Items label')
                ->rule('array'),
            self::ITEMS . '.*' => FluentRule::string()
                ->max(255),
        ];
    }
}

?>

<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

// array_merge() and a static call collide the same way as a spread: a
// folded 'tags' key from the helper would replace rules()'s own 'tags' rule.
class MergedHelperRequest extends FormRequest
{
    public function rules(): array
    {
        return array_merge(
            ['tags' => FluentRule::array()->required()->max(5)],
            self::tagRules(),
        );
    }

    private static function tagRules(): array
    {
        return [
            'tags.*' => FluentRule::string()->max(20),
        ];
    }
}

?>

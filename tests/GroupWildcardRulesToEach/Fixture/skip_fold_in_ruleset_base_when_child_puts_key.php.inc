<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\RuleSet;

// RuleSet::put() replaces a key. After a fold in the parent, the child's
// put('tags') would replace the folded each() rule as well.
class BaseTagRequest extends FormRequest
{
    public function rules(): RuleSet
    {
        return RuleSet::from([
            'tags' => FluentRule::array()->required(),
            'tags.*' => FluentRule::string()->max(20),
        ]);
    }
}

class StoreTagRequest extends BaseTagRequest
{
    public function rules(): RuleSet
    {
        return parent::rules()
            ->put('tags', FluentRule::array()->required()->max(5));
    }
}

?>

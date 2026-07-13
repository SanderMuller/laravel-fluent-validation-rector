<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Source;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentRules;
use SanderMuller\FluentValidation\RuleSet;

/**
 * Concrete base whose rules() is FluentRule-based, but a SIBLING method calls
 * $this->rules(). The converter bails this class (hasSelfRulesCallOutsideMethod
 * — renaming rules() to schema() would strand the sibling call), so it keeps
 * rules(). A child's parent::rules() must therefore be left alone.
 */
class SelfCallingBaseRequest extends FormRequest
{
    use HasFluentRules;

    public function rules(): RuleSet
    {
        return RuleSet::from([
            'title' => FluentRule::string()->required(),
        ]);
    }

    public function debugRules(): RuleSet
    {
        return $this->rules();
    }
}

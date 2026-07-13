<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Source;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule as Rule;
use SanderMuller\FluentValidation\HasFluentRules;
use SanderMuller\FluentValidation\RuleSet;

/**
 * Concrete convertible base that imports FluentRule under an ALIAS. The
 * parent-conversion probe name-resolves the parsed body, so `Rule::string()`
 * resolves to FluentRule and the base reads as converting — a child's
 * parent::rules() is then correctly rewritten to parent::schema(). Without name
 * resolution the raw parse would see `Rule` and wrongly skip the child.
 */
class AliasedFluentRuleBaseRequest extends FormRequest
{
    use HasFluentRules;

    public function rules(): RuleSet
    {
        return RuleSet::from([
            'title' => Rule::string()->required(),
        ]);
    }
}

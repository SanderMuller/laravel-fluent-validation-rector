<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Source;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentRules;
use SanderMuller\FluentValidation\RuleSet;

/**
 * Abstract base without #[FluentRules] on its rules(). The converter's abstract
 * guard holds it back, so it keeps rules() at runtime. A child fixture's
 * `parent::rules()` must therefore be left alone (the base still resolves it) —
 * the rule proves, by reflection, that this parent does NOT convert.
 */
abstract class AbstractBaseRequest extends FormRequest
{
    use HasFluentRules;

    public function rules(): RuleSet
    {
        return RuleSet::from([
            'title' => FluentRule::string()->required(),
        ]);
    }
}
